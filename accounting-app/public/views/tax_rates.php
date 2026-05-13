<?php
$title = 'Biểu thuế';
$activeMenu = 'tax_rates';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên thuế</th><th>Thuế suất</th><th>Loại thuế</th><th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<form id="dataForm">
        <input type="hidden" name="id" id="recordId">
                    <option value="">-- Chọn --</option>
                    <option value="input">Thuế đầu vào</option>
                    <option value="output">Thuế đầu ra</option>
                </select>
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
