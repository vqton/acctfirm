<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS bom (
        id VARCHAR(50) PRIMARY KEY,
        product_id VARCHAR(50) NOT NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        effective_date DATE NOT NULL,
        notes TEXT,
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES items(id),
        INDEX idx_product (product_id),
        UNIQUE KEY uq_product_version (product_id, version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS bom_lines (
        id VARCHAR(50) PRIMARY KEY,
        bom_id VARCHAR(50) NOT NULL,
        material_id VARCHAR(50) NOT NULL,
        qty_per_unit DECIMAL(15,4) NOT NULL,
        wastage_pct DECIMAL(5,2) DEFAULT 0,
        unit VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bom_id) REFERENCES bom(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES items(id),
        INDEX idx_bom (bom_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS production_orders (
        id VARCHAR(50) PRIMARY KEY,
        reference VARCHAR(50) NOT NULL UNIQUE,
        product_id VARCHAR(50) NOT NULL,
        bom_id VARCHAR(50),
        qty DECIMAL(15,2) NOT NULL,
        completed_qty DECIMAL(15,2) DEFAULT 0,
        start_date DATE,
        end_date DATE,
        due_date DATE,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        material_cost DECIMAL(15,2) DEFAULT 0,
        labor_cost DECIMAL(15,2) DEFAULT 0,
        overhead_cost DECIMAL(15,2) DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0,
        unit_cost DECIMAL(15,4) DEFAULT 0,
        notes TEXT,
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES items(id),
        FOREIGN KEY (bom_id) REFERENCES bom(id),
        INDEX idx_reference (reference),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS production_order_materials (
        id VARCHAR(50) PRIMARY KEY,
        production_order_id VARCHAR(50) NOT NULL,
        material_id VARCHAR(50) NOT NULL,
        planned_qty DECIMAL(15,2) NOT NULL DEFAULT 0,
        actual_qty DECIMAL(15,2) NOT NULL DEFAULT 0,
        unit_cost DECIMAL(15,2) DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0,
        transaction_id VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES items(id),
        INDEX idx_po (production_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS production_order_labor (
        id VARCHAR(50) PRIMARY KEY,
        production_order_id VARCHAR(50) NOT NULL,
        labor_type VARCHAR(100) DEFAULT "direct",
        actual_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
        hourly_rate DECIMAL(15,2) DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0,
        transaction_id VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
        INDEX idx_po (production_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS production_order_overhead (
        id VARCHAR(50) PRIMARY KEY,
        production_order_id VARCHAR(50) NOT NULL,
        overhead_type VARCHAR(100) NOT NULL,
        allocation_base DECIMAL(15,2) NOT NULL DEFAULT 0,
        rate DECIMAL(15,4) DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0,
        transaction_id VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
        INDEX idx_po (production_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('ALTER TABLE items MODIFY item_type VARCHAR(30) NOT NULL DEFAULT "material"');
};
