            }
            
            // Add appropriate indexes for performance
            // The uk_customer_email index already exists, no need to add it
            
            if (!$table->hasIndex('idx_customer_status')) {
                $table->addIndex(['status'], ['name' => 'idx_customer_status'])
                    ->save();
            }
            
            if (!$table->hasIndex('idx_customer_fivserv_token')) {
                $table->addIndex(['fivserv_security_token'], ['name' => 'idx_customer_fivserv_token'])
                    ->save();
            } 