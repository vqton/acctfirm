<?php
$title = 'Danh mục phòng ban';
$activeMenu = 'departments';
ob_start();
?>
</div>

    </div>
            <tr>
                <th>Mã</th><th>Tên phòng ban</th><th>Phòng ban cha</th><th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<form id="dataForm">
        <input type="hidden" name="id" id="recordId">
                <option value="">-- Không có --</option>
            </select>
        </div>
    </div>
    </div>
</form>
</div></div></div>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
