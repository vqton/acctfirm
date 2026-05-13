<?php
$title = 'Hợp đồng';
$activeMenu = 'contracts';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên hợp đồng</th><th>Loại</th><th>Đối tác</th><th>Ngày ký</th><th>Tổng giá trị</th><th>Trạng thái</th>
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
        </div>
                <option value="1">Hoạt động</option>
                <option value="0">Ngừng</option>
            </select>
        </div>
    </div>
    </div>
</form>
</div></div></div>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
