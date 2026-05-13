<?php
$title = 'Tỷ giá ngoại tệ';
$activeMenu = 'exchange_rates';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã ngoại tệ</th><th>Tên ngoại tệ</th><th>Tỷ giá</th><th>Ngày áp dụng</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<form id="dataForm">
        <input type="hidden" name="id" id="recordId">
    </div>
    </div>
</form>
</div></div></div>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
