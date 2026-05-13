<?php
$title = 'Công cụ dụng cụ';
$activeMenu = 'ccdc';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên</th><th>Đơn vị</th><th>Số lượng</th><th>Hình thức</th><th>Tổng giá trị</th><th>Đã phân bổ</th><th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<form id="dataForm">
        <input type="hidden" name="id" id="recordId">
        </div>
                    <option value="">-- Chọn --</option>
                    <option value="linear">Đường thẳng</option>
                    <option value="one_time">Một lần</option>
                </select>
            </div>
        </div>
                    <option value="1">Hoạt động</option>
                    <option value="0">Ngừng</option>
                </select>
            </div>
        </div>
    </div>
    </div>
</form>
</div></div></div>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
