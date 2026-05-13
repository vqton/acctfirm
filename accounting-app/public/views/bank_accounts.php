<?php
$title = 'Tài khoản ngân hàng';
$activeMenu = 'bank_accounts';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Ngân hàng</th><th>Số TK</th><th>Chủ TK</th><th>Chi nhánh</th><th>Loại tiền</th><th>Số dư đầu</th><th>Trạng thái</th>
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
