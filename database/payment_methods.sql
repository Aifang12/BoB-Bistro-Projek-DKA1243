USE bistro_db;

-- Keep FPX for existing records; new orders use Online Banking or an e-wallet.
ALTER TABLE payments
    MODIFY payment_method ENUM(
        'Tunai',
        'FPX',
        'Online Banking',
        'TNG eWallet',
        'Boost',
        'ShopeePay'
    ) NOT NULL;
