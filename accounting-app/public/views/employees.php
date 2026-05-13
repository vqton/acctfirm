<?php
$title = 'Danh mục nhân viên';
$activeMenu = 'employees';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Họ tên</th><th>Phòng ban</th><th>Chức vụ</th><th>Điện thoại</th><th>Trạng thái</th>
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
        </div>
    </div>
    </div>
</form>
</div></div></div>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
