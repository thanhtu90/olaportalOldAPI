<?php
/**
 * Shared worker for draining `quickbooks_export_queue` into QuickBooks Online.
 *
 * Used by both the one-shot `quickbook.php` entry point (enqueue + flush in one
 * call) and the manual `qb_export.php` retry endpoint.
 *
 * Key correctness rules:
 *   - We only DELETE a queue row after a 2xx response from QBO (qb_export.php
 *     originally deleted on every iteration, which silently dropped rows on
 *     auth/API failures).
 *   - If no valid (non-expired) token exists for the vendor, we DO NOT touch
 *     the queue; rows remain for the next retry once the merchant re-auth's.
 */

if (!function_exists('flushQuickbooksQueue')) {

    function flushQuickbooksQueue(PDO $pdo, $vendorId, array $config)
    {
        $summary = [
            'pushed'    => 0,
            'failed'    => 0,
            'skipped'   => 0,
            'reason'    => null,
            'errors'    => [],
        ];

        $vendorId = (int) $vendorId;
        if ($vendorId <= 0) {
            $summary['reason'] = 'invalid_vendor_id';
            return $summary;
        }

        $currentTs = (int) time();

        $stmtToken = $pdo->prepare("
            SELECT token_key, realm_id, token_type
            FROM quickbooks_token_cred
            WHERE vendor_id = :vendor_id
              AND token_expire > :current_ts
            ORDER BY lastmod DESC
            LIMIT 1
        ");
        $stmtToken->execute([
            ':vendor_id'  => $vendorId,
            ':current_ts' => $currentTs,
        ]);
        $token = $stmtToken->fetch(PDO::FETCH_ASSOC);

        if (!$token || empty($token['token_key']) || empty($token['realm_id'])) {
            $stmtPending = $pdo->prepare("
                SELECT COUNT(*) FROM quickbooks_export_queue WHERE vendor_id = :vendor_id
            ");
            $stmtPending->execute([':vendor_id' => $vendorId]);
            $summary['skipped'] = (int) $stmtPending->fetchColumn();
            $summary['reason']  = 'no_valid_token';
            error_log("[qb_export_lib] vendor={$vendorId} skipped flush: no valid token (pending={$summary['skipped']})");
            return $summary;
        }

        $tokenKey = $token['token_key'];
        $realmId  = $token['realm_id'];

        $stmtEmail = $pdo->prepare("SELECT email FROM accounts WHERE id = :vendor_id");
        $stmtEmail->execute([':vendor_id' => $vendorId]);
        $email = $stmtEmail->fetchColumn();

        $stmtQueue = $pdo->prepare("
            SELECT id, payload
            FROM quickbooks_export_queue
            WHERE vendor_id = :vendor_id
            ORDER BY lastmod ASC, id ASC
        ");
        $stmtQueue->execute([':vendor_id' => $vendorId]);

        $url = $config['baseApiUrl'] . "/{$realmId}" . $config['batchSuffix'] . $config['minorVersion'];
        $curl = curl_init();

        $stmtDelete = $pdo->prepare("DELETE FROM quickbooks_export_queue WHERE id = :id");

        while ($row = $stmtQueue->fetch(PDO::FETCH_ASSOC)) {
            $queueId = (int) $row['id'];
            $decodedPayload = json_decode($row['payload']);

            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $tokenKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($decodedPayload),
            ]);

            $curlStart  = microtime(true);
            $response   = curl_exec($curl);
            $curlElapsed = microtime(true) - $curlStart;

            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErrno  = curl_errno($curl);
            $curlError  = curl_error($curl);

            if ($curlErrno) {
                $summary['failed']++;
                $summary['errors'][] = [
                    'queue_id' => $queueId,
                    'error'    => 'curl_error: ' . $curlError,
                ];
                error_log("[qb_export_lib] vendor={$vendorId} queue_id={$queueId} curl_errno={$curlErrno} error={$curlError}");
                continue;
            }

            if ($httpStatus < 200 || $httpStatus >= 300) {
                $summary['failed']++;
                $summary['errors'][] = [
                    'queue_id' => $queueId,
                    'http'     => $httpStatus,
                    'body'     => is_string($response) ? substr($response, 0, 2000) : null,
                ];
                error_log(sprintf(
                    "[qb_export_lib] vendor=%d queue_id=%d HTTP %d in %.3fs body=%s",
                    $vendorId,
                    $queueId,
                    $httpStatus,
                    $curlElapsed,
                    is_string($response) ? substr($response, 0, 500) : ''
                ));
                continue;
            }

            $stmtDelete->execute([':id' => $queueId]);
            $summary['pushed']++;
            error_log(sprintf(
                "[qb_export_lib] vendor=%d queue_id=%d pushed OK (HTTP %d, %.3fs)",
                $vendorId,
                $queueId,
                $httpStatus,
                $curlElapsed
            ));
        }

        curl_close($curl);

        if ($summary['pushed'] > 0 && !empty($email)) {
            @mail(
                $email,
                'OlaPortal Quickbook Export finished',
                'The Quickbook Exporting Operation has finished. Please go to your Quickbook Online account to check the exported data',
                "From: <noreply@olapay.us>\r\n"
            );
        }

        return $summary;
    }
}
