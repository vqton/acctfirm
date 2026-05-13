<?php
$title = 'Danh mục kho';
$activeMenu = 'warehouses';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên kho</th><th>Địa chỉ</th><th>Trạng thái</th>
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
