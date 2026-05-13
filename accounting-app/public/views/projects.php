<?php
$title = 'Dự án / Công trình';
$activeMenu = 'projects';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên dự án</th><th>Khách hàng</th><th>Ngày bắt đầu</th><th>Ngày kết thúc</th><th>Ngân sách</th><th>Trạng thái</th>
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
