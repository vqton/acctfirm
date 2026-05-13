<?php
$title = 'Chính sách khấu hao';
$activeMenu = 'depreciation_policies';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên chính sách</th><th>Phương pháp</th><th>Thời gian mặc định (tháng)</th><th>Tỷ lệ thu hồi (%)</th><th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<form id="dataForm">
        <input type="hidden" name="id" id="recordId">
                    <option value="">-- Chọn --</option>
                    <option value="straight_line">Đường thẳng</option>
                    <option value="declining_balance">Số dư giảm dần</option>
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
