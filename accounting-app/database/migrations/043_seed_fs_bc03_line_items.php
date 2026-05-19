<?php
return function (PDO $pdo) {
    $bc03 = [
        // === SECTION I: HOẠT ĐỘNG KINH DOANH (Indirect Method) ===
        ['BC03','01',null,'Lợi nhuận trước thuế','from_bc02',null,'positive',1,0,0],
        ['BC03','02',null,'Khấu hao TSCĐ và BĐSĐT','manual',null,'positive',2,0,0],
        ['BC03','03',null,'Các khoản dự phòng','account_delta','2291,2292,2293,2294,2295','positive',3,0,0],
        ['BC03','04',null,'Lãi/lỗ chênh lệch tỷ giá hối đoái do đánh giá lại các khoản mục tiền tệ có gốc ngoại tệ','manual',null,'positive',4,0,0],
        ['BC03','05',null,'Lãi/lỗ từ hoạt động đầu tư, tài chính','investment_adjust',null,'positive',5,0,0],
        ['BC03','06',null,'Chi phí đi vay','from_bc02_24',null,'positive',6,0,0],
        ['BC03','07',null,'Các khoản điều chỉnh khác','manual',null,'positive',7,0,0],
        ['BC03','08',null,'Lợi nhuận từ HĐKD trước thay đổi vốn lưu động','sum','01,02,03,04,05,06,07','positive',8,1,0],

        ['BC03','09',null,'Tăng, giảm các khoản phải thu','delta_neg','131,1362,1363,1368,1388,334,338,141,244,133','positive',9,0,0],
        ['BC03','10',null,'Tăng, giảm hàng tồn kho','delta_neg','151,152,153,154,155,156,157,158','positive',10,0,0],
        ['BC03','11',null,'Tăng, giảm các khoản phải trả (không kể lãi vay phải trả, thuế TNDN phải nộp)','delta_pos','331,333,334,335,336,337,338,344','positive',11,0,0],
        ['BC03','12',null,'Tăng, giảm chi phí chờ phân bổ','delta_neg','242','positive',12,0,0],
        ['BC03','13',null,'Tăng, giảm chứng khoán kinh doanh','delta_neg','121','positive',13,0,0],
        ['BC03','14',null,'Chi phí đi vay đã trả','manual',null,'positive',14,0,0],
        ['BC03','15',null,'Thuế TNDN đã nộp','manual',null,'positive',15,0,0],
        ['BC03','16',null,'Tiền thu khác từ hoạt động kinh doanh','manual',null,'positive',16,0,0],
        ['BC03','17',null,'Tiền chi khác cho hoạt động kinh doanh','manual',null,'positive',17,0,0],

        ['BC03','20',null,'Lưu chuyển tiền thuần từ hoạt động kinh doanh','sum','08,09,10,11,12,13,14,15,16,17','positive',18,1,0],

        // === SECTION II: HOẠT ĐỘNG ĐẦU TƯ ===
        ['BC03','21',null,'Tiền chi để mua sắm, xây dựng TSCĐ và các tài sản dài hạn khác','delta_neg','211,213,217,241','positive',19,0,0],
        ['BC03','22',null,'Tiền thu từ thanh lý, nhượng bán TSCĐ và các tài sản dài hạn khác','manual',null,'positive',20,0,0],
        ['BC03','23',null,'Tiền chi cho vay, mua các công cụ nợ của đơn vị khác','delta_neg','1281,1282,1283,1288','positive',21,0,0],
        ['BC03','24',null,'Tiền thu hồi cho vay, bán lại các công cụ nợ của đơn vị khác','manual',null,'positive',22,0,0],
        ['BC03','25',null,'Tiền chi đầu tư góp vốn vào đơn vị khác','delta_neg','221,222,2281','positive',23,0,0],
        ['BC03','26',null,'Tiền thu hồi đầu tư góp vốn vào đơn vị khác','manual',null,'positive',24,0,0],
        ['BC03','27',null,'Tiền thu lãi cho vay, cổ tức và lợi nhuận được chia','manual',null,'positive',25,0,0],

        ['BC03','30',null,'Lưu chuyển tiền thuần từ hoạt động đầu tư','sum','21,22,23,24,25,26,27','positive',26,1,0],

        // === SECTION III: HOẠT ĐỘNG TÀI CHÍNH ===
        ['BC03','31',null,'Tiền thu từ phát hành cổ phiếu, nhận vốn góp của chủ sở hữu','delta_pos','4111','positive',27,0,0],
        ['BC03','32',null,'Tiền trả lại vốn góp cho các chủ sở hữu, mua lại cổ phiếu đã phát hành','delta_neg','419','positive',28,0,0],
        ['BC03','33',null,'Tiền thu từ đi vay','delta_pos','3411,3431','positive',29,0,0],
        ['BC03','34',null,'Tiền trả nợ gốc vay','delta_neg_only','3411,3431','positive',30,0,0],
        ['BC03','35',null,'Tiền trả nợ gốc thuê tài chính','manual',null,'positive',31,0,0],
        ['BC03','36',null,'Cổ tức, lợi nhuận đã trả cho chủ sở hữu','manual',null,'positive',32,0,0],

        ['BC03','40',null,'Lưu chuyển tiền thuần từ hoạt động tài chính','sum','31,32,33,34,35,36','positive',33,1,0],

        // === SUMMARY ===
        ['BC03','50',null,'Lưu chuyển tiền thuần trong kỳ','calculated','20+30+40','positive',34,0,1],
        ['BC03','60',null,'Tiền và tương đương tiền đầu kỳ','cash_begin',null,'positive',35,0,0],
        ['BC03','61',null,'Ảnh hưởng của thay đổi tỷ giá hối đoái quy đổi ngoại tệ','manual',null,'positive',36,0,0],
        ['BC03','70',null,'Tiền và tương đương tiền cuối kỳ','calculated','50+60+61','positive',37,0,1],
    ];

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO fs_line_items (statement, ma_so, parent_ma_so, name_vi, formula_type, formula_detail, sign_convention, display_order, is_control, is_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($bc03 as $r) {
        $insert->execute($r);
    }
};
