<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS fs_line_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        statement VARCHAR(10) NOT NULL,
        ma_so VARCHAR(10) NOT NULL,
        parent_ma_so VARCHAR(10) DEFAULT NULL,
        name_vi VARCHAR(255) NOT NULL,
        formula_type VARCHAR(20) DEFAULT "account",
        formula_detail TEXT DEFAULT NULL,
        sign_convention VARCHAR(10) DEFAULT "positive",
        display_order INT DEFAULT 0,
        is_control TINYINT(1) DEFAULT 0,
        is_total TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_stmt_ma (statement, ma_so)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS fs_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        statement VARCHAR(10) NOT NULL,
        period_code VARCHAR(10) NOT NULL,
        period_end_date DATE NOT NULL,
        data JSON NOT NULL,
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_stmt_period (statement, period_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // BC 01 — Tài sản (Assets)
    $bc01 = [
        // Cash
        ['BC01','111',null,'Tiền','account','111,112,113','positive',1,0,0],
        ['BC01','112',null,'Các khoản tương đương tiền','account','1281,1288','positive',2,0,0],
        ['BC01','110','100','Tiền và tương đương tiền','sum','111,112','positive',3,1,0],

        // Short-term investments
        ['BC01','121',null,'Chứng khoán kinh doanh','account','121','positive',4,0,0],
        ['BC01','122',null,'Dự phòng giảm giá CK KD','account','2291','negative',5,0,0],
        ['BC01','123',null,'Đầu tư nắm giữ đến đáo hạn NH','account','1281,1282,1283,1288','positive',6,0,0],
        ['BC01','124',null,'Dự phòng đầu tư NH đến đáo hạn','account','2292','negative',7,0,0],
        ['BC01','125',null,'Đầu tư ngắn hạn khác','account','2281','positive',8,0,0],
        ['BC01','126',null,'Dự phòng tổn thất đầu tư NH khác','account','2292','negative',9,0,0],
        ['BC01','120','100','Đầu tư tài chính ngắn hạn','sum','121,122,123,124,125,126','positive',10,1,0],

        // Receivables
        ['BC01','131',null,'Phải thu KH ngắn hạn','account','131','positive',11,0,0],
        ['BC01','132',null,'Trả trước cho NB ngắn hạn','account','331','negative',12,0,0],
        ['BC01','133',null,'Phải thu nội bộ ngắn hạn','account','1362,1363,1368','positive',13,0,0],
        ['BC01','134',null,'Phải thu theo tiến độ HĐXD','account','337','positive',14,0,0],
        ['BC01','135',null,'Phải thu ngắn hạn khác','account','1388,334,338,141,244','positive',15,0,0],
        ['BC01','136',null,'Dự phòng phải thu NH khó đòi','account','2293','negative',16,0,0],
        ['BC01','137',null,'Tài sản thiếu chờ xử lý','account','1381','positive',17,0,0],
        ['BC01','130','100','Phải thu ngắn hạn','sum','131,132,133,134,135,136,137','positive',18,1,0],

        // Inventory
        ['BC01','141',null,'Hàng tồn kho','account','151,152,153,154,155,156,157,158','positive',19,0,0],
        ['BC01','142',null,'Dự phòng giảm giá HTK','account','2294','negative',20,0,0],
        ['BC01','140','100','Hàng tồn kho','sum','141,142','positive',21,1,0],

        // Biological assets short-term
        ['BC01','151',null,'TS sinh học ngắn hạn','account','2152,2153','positive',22,0,0],
        ['BC01','152',null,'Dự phòng tổn thất TSHH NH','account','2295','negative',23,0,0],
        ['BC01','150','100','Tài sản sinh học ngắn hạn','sum','151,152','positive',24,1,0],

        // Other current assets
        ['BC01','161',null,'Chi phí chờ phân bổ NH','account','242','positive',25,0,0],
        ['BC01','162',null,'Thuế GTGT được khấu trừ','account','133','positive',26,0,0],
        ['BC01','163',null,'Thuế và khoản khác phải thu NN','account','1383,333','positive',27,0,0],
        ['BC01','164',null,'Giao dịch mua bán lại TPCP','account','171','positive',28,0,0],
        ['BC01','165',null,'Tài sản ngắn hạn khác','account','2288','positive',29,0,0],
        ['BC01','160','100','Tài sản ngắn hạn khác','sum','161,162,163,164,165','positive',30,1,0],

        ['BC01','100','280','TÀI SẢN NGẮN HẠN','sum','110,120,130,140,150,160','positive',31,1,0],

        // Long-term receivables
        ['BC01','211',null,'Phải thu KH dài hạn','account','131','positive',32,0,0],
        ['BC01','212',null,'Trả trước cho NB dài hạn','account','331','negative',33,0,0],
        ['BC01','213',null,'Vốn KD ở đơn vị trực thuộc','account','1361','positive',34,0,0],
        ['BC01','214',null,'Phải thu nội bộ dài hạn','account','1362,1363,1368','positive',35,0,0],
        ['BC01','215',null,'Phải thu dài hạn khác','account','1388,334,338,141,244','positive',36,0,0],
        ['BC01','216',null,'Dự phòng phải thu DH khó đòi','account','2293','negative',37,0,0],
        ['BC01','210','200','Phải thu dài hạn','sum','211,212,213,214,215,216','positive',38,1,0],

        // Fixed assets
        ['BC01','222',null,'Nguyên giá TSCĐ hữu hình','account','211','positive',39,0,0],
        ['BC01','223',null,'Hao mòn TSCĐ hữu hình','account','2141','negative',40,0,0],
        ['BC01','221','220','TSCĐ hữu hình','sum','222,223','positive',41,1,0],
        ['BC01','225',null,'Nguyên giá TSCĐ thuê TC','account','212','positive',42,0,0],
        ['BC01','226',null,'Hao mòn TSCĐ thuê TC','account','2142','negative',43,0,0],
        ['BC01','224','220','TSCĐ thuê tài chính','sum','225,226','positive',44,1,0],
        ['BC01','228',null,'Nguyên giá TSCĐ vô hình','account','213','positive',45,0,0],
        ['BC01','229',null,'Hao mòn TSCĐ vô hình','account','2143','negative',46,0,0],
        ['BC01','227','220','TSCĐ vô hình','sum','228,229','positive',47,1,0],
        ['BC01','220','200','TÀI SẢN CỐ ĐỊNH','sum','221,224,227','positive',48,1,0],

        // Biological assets long-term
        ['BC01','231',null,'TS sinh học dài hạn','account','215','positive',49,0,0],
        ['BC01','236',null,'Súc vật nuôi lấy SP 1 lần DH','account','2152','positive',50,0,0],
        ['BC01','237',null,'Cây trồng theo mùa vụ DH','account','2153','positive',51,0,0],
        ['BC01','238',null,'Dự phòng tổn thất TSHH DH','account','2295','negative',52,0,0],
        ['BC01','230','200','Tài sản sinh học dài hạn','sum','231,236,237,238','positive',53,1,0],

        ['BC01','241',null,'Nguyên giá BĐS đầu tư','account','217','positive',54,0,0],
        ['BC01','242',null,'Hao mòn BĐS đầu tư','account','2147','negative',55,0,0],
        ['BC01','240','200','Bất động sản đầu tư','sum','241,242','positive',56,1,0],

        ['BC01','251',null,'CP SXKD dở dang dài hạn','account','154','positive',57,0,0],
        ['BC01','252',null,'CP XDCB dở dang','account','241','positive',58,0,0],
        ['BC01','250','200','Tài sản dở dang dài hạn','sum','251,252','positive',59,1,0],

        // Long-term investments
        ['BC01','261',null,'Đầu tư vào công ty con','account','221','positive',60,0,0],
        ['BC01','262',null,'Đầu tư vào liên doanh, liên kết','account','222','positive',61,0,0],
        ['BC01','263',null,'Đầu tư góp vốn vào đơn vị khác','account','2281','positive',62,0,0],
        ['BC01','264',null,'Dự phòng tổn thất đầu tư DH','account','2292','negative',63,0,0],
        ['BC01','265',null,'Đầu tư nắm giữ đến đáo hạn DH','account','1281,1282,1283,1288','positive',64,0,0],
        ['BC01','266',null,'Dự phòng đầu tư đến đáo hạn DH','account','2292','negative',65,0,0],
        ['BC01','260','200','Đầu tư tài chính dài hạn','sum','261,262,263,264,265,266','positive',66,1,0],

        // Other long-term assets
        ['BC01','271',null,'CP chờ phân bổ dài hạn','account','242','positive',67,0,0],
        ['BC01','272',null,'Tài sản thuế TN hoãn lại','account','243','positive',68,0,0],
        ['BC01','273',null,'Thiết bị, vật tư, phụ tùng thay thế DH','account','153','positive',69,0,0],
        ['BC01','274',null,'Tài sản dài hạn khác','account','2288','positive',70,0,0],
        ['BC01','270','200','Tài sản dài hạn khác','sum','271,272,273,274','positive',71,1,0],

        ['BC01','200','280','TÀI SẢN DÀI HẠN','sum','210,220,230,240,250,260,270','positive',72,1,0],

        ['BC01','280',null,'TỔNG CỘNG TÀI SẢN','sum','100,200','positive',73,0,1],

        // Liabilities — Current
        ['BC01','311',null,'Phải trả NB ngắn hạn','account','331','positive',74,0,0],
        ['BC01','312',null,'Người mua trả tiền trước NH','account','131','negative',75,0,0],
        ['BC01','313',null,'Phải trả cổ tức, LN','account','332','positive',76,0,0],
        ['BC01','314',null,'Thuế và các khoản phải nộp NN','account','333','positive',77,0,0],
        ['BC01','315',null,'Phải trả người lao động','account','334','positive',78,0,0],
        ['BC01','316',null,'Chi phí phải trả NH','account','335','positive',79,0,0],
        ['BC01','317',null,'Phải trả nội bộ NH','account','3362,3363,3368','positive',80,0,0],
        ['BC01','318',null,'Phải trả theo tiến độ HĐXD','account','337','positive',81,0,0],
        ['BC01','319',null,'Doanh thu chờ phân bổ NH','account','3387','positive',82,0,0],
        ['BC01','320',null,'Phải trả NH khác','account','338,138,344','positive',83,0,0],
        ['BC01','321',null,'Vay và nợ thuê TC NH','account','341,3431','positive',84,0,0],
        ['BC01','322',null,'Dự phòng phải trả NH','account','352','positive',85,0,0],
        ['BC01','323',null,'Quỹ khen thưởng, phúc lợi','account','353','positive',86,0,0],
        ['BC01','324',null,'Quỹ bình ổn giá','account','357','positive',87,0,0],
        ['BC01','325',null,'Giao dịch mua bán lại TPCP','account','171','negative',88,0,0],
        ['BC01','310','300','NỢ NGẮN HẠN','sum','311,312,313,314,315,316,317,318,319,320,321,322,323,324,325','positive',89,1,0],

        // Liabilities — Long-term
        ['BC01','331',null,'Phải trả NB dài hạn','account','331','positive',90,0,0],
        ['BC01','332',null,'Người mua trả tiền trước DH','account','131','negative',91,0,0],
        ['BC01','333',null,'Thuế và khoản phải nộp NN DH','account','333','positive',92,0,0],
        ['BC01','334',null,'Chi phí phải trả DH','account','335','positive',93,0,0],
        ['BC01','335',null,'Phải trả nội bộ về vốn KD','account','3361','positive',94,0,0],
        ['BC01','336',null,'Phải trả nội bộ DH','account','3362,3363,3368','positive',95,0,0],
        ['BC01','337',null,'Doanh thu chờ phân bổ DH','account','3387','positive',96,0,0],
        ['BC01','338',null,'Phải trả DH khác','account','338,344','positive',97,0,0],
        ['BC01','339',null,'Vay và nợ thuê TC DH','account','341,3431','positive',98,0,0],
        ['BC01','340',null,'Trái phiếu chuyển đổi','account','3432','positive',99,0,0],
        ['BC01','341',null,'Cổ phiếu ưu đãi','account','41112','positive',100,0,0],
        ['BC01','342',null,'Thuế TN hoãn lại phải trả','account','347','positive',101,0,0],
        ['BC01','343',null,'Dự phòng phải trả DH','account','352','positive',102,0,0],
        ['BC01','344',null,'Quỹ phát triển KH&CN','account','356','positive',103,0,0],
        ['BC01','330','300','NỢ DÀI HẠN','sum','331,332,333,334,335,336,337,338,339,340,341,342,343,344','positive',104,1,0],

        ['BC01','300','440','NỢ PHẢI TRẢ','sum','310,330','positive',105,1,0],

        // Equity
        ['BC01','411',null,'Vốn góp của chủ sở hữu','account','4111','positive',106,0,0],
        ['BC01','412',null,'Thặng dư vốn','account','4112','positive',107,0,0],
        ['BC01','413',null,'Quyền chọn chuyển đổi TP','account','4113','positive',108,0,0],
        ['BC01','414',null,'Vốn khác của CSH','account','4118','positive',109,0,0],
        ['BC01','415',null,'Cổ phiếu mua lại của chính mình','account','419','negative',110,0,0],
        ['BC01','416',null,'Chênh lệch đánh giá lại TS','account','412','positive',111,0,0],
        ['BC01','417',null,'Chênh lệch tỷ giá hối đoái','account','413','positive',112,0,0],
        ['BC01','418',null,'Quỹ đầu tư phát triển','account','414','positive',113,0,0],
        ['BC01','419',null,'Quỹ khác thuộc VCSH','account','418','positive',114,0,0],
        ['BC01','420',null,'LN sau thuế chưa phân phối','account','4211,4212','positive',115,0,0],
        ['BC01','411','400','VỐN GÓP CỦA CSH','sum','411,412,413,414,415,416,417,418,419,420','positive',116,0,0],

        ['BC01','400','440','VỐN CHỦ SỞ HỮU','sum','411','positive',117,1,0],

        ['BC01','440',null,'TỔNG CỘNG NGUỒN VỐN','sum','300,400','positive',118,0,1],
    ];

    // BC 02 — Income Statement
    $bc02 = [
        ['BC02','01',null,'Doanh thu bán hàng và CCDV','account','511','positive',1,0,0],
        ['BC02','02',null,'Các khoản giảm trừ doanh thu','account','521','positive',2,0,0],
        ['BC02','10',null,'Doanh thu thuần về BH và CCDV','calculated','01-02','positive',3,1,0],
        ['BC02','11',null,'Giá vốn hàng bán','account','632','positive',4,0,0],
        ['BC02','20',null,'Lợi nhuận gộp về BH và CCDV','calculated','10-11','positive',5,1,0],
        ['BC02','21',null,'Lãi/lỗ từ bán, thanh lý BĐS ĐT','account','511,632','positive',6,0,0],
        ['BC02','22',null,'Doanh thu hoạt động tài chính','account','515','positive',7,0,0],
        ['BC02','23',null,'Chi phí tài chính','account','635','positive',8,0,0],
        ['BC02','24',null,'Trong đó: Chi phí đi vay','account','635','positive',9,0,0],
        ['BC02','25',null,'Chi phí bán hàng','account','641','positive',10,0,0],
        ['BC02','26',null,'Chi phí quản lý doanh nghiệp','account','642','positive',11,0,0],
        ['BC02','30',null,'Lợi nhuận thuần từ HĐKD','calculated','20+21+22-(23+25+26)','positive',12,1,0],
        ['BC02','31',null,'Thu nhập khác','account','711','positive',13,0,0],
        ['BC02','32',null,'Chi phí khác','account','811','positive',14,0,0],
        ['BC02','40',null,'Lợi nhuận khác','calculated','31-32','positive',15,1,0],
        ['BC02','50',null,'Tổng LNKT trước thuế','calculated','30+40','positive',16,1,0],
        ['BC02','51',null,'Chi phí thuế TNDN hiện hành','account','8211','positive',17,0,0],
        ['BC02','52',null,'Chi phí thuế TNDN hoãn lại','account','8212','positive',18,0,0],
        ['BC02','60',null,'Lợi nhuận sau thuế TNDN','calculated','50-(51+52)','positive',19,1,1],
        ['BC02','70',null,'Lãi cơ bản trên cổ phiếu','manual',null,'positive',20,0,0],
        ['BC02','71',null,'Lãi suy giảm trên cổ phiếu','manual',null,'positive',21,0,0],
    ];

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO fs_line_items (statement, ma_so, parent_ma_so, name_vi, formula_type, formula_detail, sign_convention, display_order, is_control, is_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach (array_merge($bc01, $bc02) as $r) {
        $insert->execute($r);
    }
};
