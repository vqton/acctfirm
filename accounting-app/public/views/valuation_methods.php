<?php
$title = 'Phương pháp tính giá';
$activeMenu = 'valuation_methods';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên phương pháp</th><th>Mô tả</th><th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<form id="dataForm">
        <input type="hidden" name="id" id="recordId">
                <option value="1">Hoạt động</option>
                <option value="0">Ngừng</option>
            </select>
        </div>
    </div>
    </div>
</form>
</div></div></div>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
