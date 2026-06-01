<?php
// Seed chỉ tiêu BC03 — Phương pháp trực tiếp (Direct Method)
return function (PDO $pdo) {
    $items = [
        // === I. LƯU CHUYỂN TIỀN TỪ HOẠT ĐỘNG KINH DOANH ===
        ['BC03D','01',null,'Tiền thu từ khách hàng','direct_receipt','131,511','positive',1,0,0],
        ['BC03D','02',null,'Tiền chi trả cho nhà cung cấp','direct_payment','331,152,153,156','positive',2,0,0],
        ['BC03D','03',null,'Tiền chi trả cho người lao động','direct_payment','334,3383','positive',3,0,0],
        ['BC03D','04',null,'Tiền chi trả lãi vay','direct_payment','635','positive',4,0,0],
        ['BC03D','05',null,'Tiền chi nộp thuế TNDN','direct_payment','3334','positive',5,0,0],
        ['BC03D','06',null,'Tiền thu khác từ hoạt động kinh doanh','direct_receipt_other','','positive',6,0,0],
        ['BC03D','07',null,'Tiền chi khác cho hoạt động kinh doanh','direct_payment_other','','positive',7,0,0],
        ['BC03D','10',null,'Lưu chuyển tiền thuần từ HĐKD','sum','01,02,03,04,05,06,07','positive',8,1,0],

        // === II. LƯU CHUYỂN TIỀN TỪ HOẠT ĐỘNG ĐẦU TƯ ===
        ['BC03D','21',null,'Tiền chi mua sắm TSCĐ','direct_payment','211,213,217,241','positive',9,0,0],
        ['BC03D','22',null,'Tiền thu từ thanh lý TSCĐ','direct_receipt','711','positive',10,0,0],
        ['BC03D','23',null,'Tiền chi cho vay','direct_payment','1281,1282,1283','positive',11,0,0],
        ['BC03D','24',null,'Tiền thu hồi cho vay','direct_receipt','1281,1282,1283','positive',12,0,0],
        ['BC03D','25',null,'Tiền chi đầu tư góp vốn','direct_payment','221,222,2281','positive',13,0,0],
        ['BC03D','26',null,'Tiền thu hồi đầu tư','direct_receipt','221,222,2281','positive',14,0,0],
        ['BC03D','27',null,'Tiền thu lãi vay, cổ tức','direct_receipt','515,635','positive',15,0,0],
        ['BC03D','30',null,'Lưu chuyển tiền thuần từ HĐĐT','sum','21,22,23,24,25,26,27','positive',16,1,0],

        // === III. LƯU CHUYỂN TIỀN TỪ HOẠT ĐỘNG TÀI CHÍNH ===
        ['BC03D','31',null,'Tiền thu từ phát hành CP, nhận vốn góp','direct_receipt','4111,4112','positive',17,0,0],
        ['BC03D','32',null,'Tiền trả lại vốn góp','direct_payment','419','positive',18,0,0],
        ['BC03D','33',null,'Tiền thu từ đi vay','direct_receipt','3411,3431','positive',19,0,0],
        ['BC03D','34',null,'Tiền trả nợ gốc vay','direct_payment','3411,3431','positive',20,0,0],
        ['BC03D','35',null,'Tiền trả nợ gốc thuê tài chính','direct_payment','3412','positive',21,0,0],
        ['BC03D','36',null,'Cổ tức đã trả cho CSH','direct_payment','421','positive',22,0,0],
        ['BC03D','40',null,'Lưu chuyển tiền thuần từ HĐTC','sum','31,32,33,34,35,36','positive',23,1,0],

        // === TỔNG HỢP ===
        ['BC03D','50',null,'Lưu chuyển tiền thuần trong kỳ','sum','10,30,40','positive',24,0,1],
        ['BC03D','60',null,'Tiền và tương đương tiền đầu kỳ','cash_begin',null,'positive',25,0,0],
        ['BC03D','70',null,'Tiền và tương đương tiền cuối kỳ','sum','50,60','positive',26,0,1],
    ];

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO fs_line_items (statement, ma_so, parent_ma_so, name_vi, formula_type, formula_detail, sign_convention, display_order, is_control, is_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $r) {
        $insert->execute($r);
    }
};
