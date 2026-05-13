<?php
$title = 'Tài sản cố định';
$activeMenu = 'fixed_assets';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên TSCĐ</th><th>Ngày mua</th><th>Nguyên giá</th><th>PP khấu hao</th><th>Thời gian (tháng)</th><th>Giá trị còn lại</th><th>Trạng thái</th>
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
