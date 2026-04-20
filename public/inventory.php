<?php
require_once "../config/db.php";
requireLogin();

// Handle Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $quantity = (int) $_POST['quantity'];
    $unit = trim($_POST['unit']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    $reorder_level = (int) $_POST['reorder_level'];

    $stmt = $conn->prepare("INSERT INTO inventory_items (name, category, description, quantity, unit, expiry_date, reorder_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisss", $name, $category, $description, $quantity, $unit, $expiry_date, $reorder_level);

    if ($stmt->execute()) {
        $item_id = $stmt->insert_id;
        $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (item_id, type, quantity, remarks, user_id) VALUES (?, 'Stock In', ?, 'Initial Stock', ?)");
        $log_stmt->bind_param("iii", $item_id, $quantity, $_SESSION['user_id']);
        $log_stmt->execute();
        $_SESSION['success_message'] = "Item added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding item: " . $conn->error;
    }
    header("Location: inventory.php");
    exit();
}

// Handle Update Stock / Adjust
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $item_id = (int) $_POST['item_id'];
    $type = $_POST['type'];
    $quantity = (int) $_POST['quantity'];
    $remarks = trim($_POST['remarks']);

    $curr_stmt = $conn->prepare("SELECT quantity FROM inventory_items WHERE id = ?");
    $curr_stmt->bind_param("i", $item_id);
    $curr_stmt->execute();
    $curr = $curr_stmt->get_result()->fetch_assoc();
    $current_qty = $curr['quantity'];

    $new_qty = $current_qty;
    if ($type === 'Stock In')
        $new_qty += $quantity;
    elseif ($type === 'Stock Out' || $type === 'Dispensed')
        $new_qty -= $quantity;
    elseif ($type === 'Adjustment')
        $new_qty += $quantity;

    if ($new_qty < 0)
        $new_qty = 0;

    $update_stmt = $conn->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $new_qty, $item_id);

    if ($update_stmt->execute()) {
        $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (item_id, type, quantity, remarks, user_id) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->bind_param("isisi", $item_id, $type, $quantity, $remarks, $_SESSION['user_id']);
        $log_stmt->execute();
        $_SESSION['success_message'] = "Stock updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating stock: " . $conn->error;
    }
    header("Location: inventory.php");
    exit();
}

// Handle Archive
if (isset($_GET['archive_id'])) {
    $id = (int) $_GET['archive_id'];
    $conn->query("UPDATE inventory_items SET is_archived = 1, archived_at = NOW() WHERE id = $id");
    $_SESSION['success_message'] = "Item archived successfully.";
    header("Location: inventory.php");
    exit();
}

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clauses = ["is_archived = 0"];
if ($search)
    $where_clauses[] = "(name LIKE '%$search%' OR description LIKE '%$search%')";
if ($category_filter)
    $where_clauses[] = "category = '$category_filter'";
if ($status_filter === 'low_stock')
    $where_clauses[] = "quantity <= 10 AND quantity > 0";
elseif ($status_filter === 'out_of_stock')
    $where_clauses[] = "quantity = 0";
elseif ($status_filter === 'expired')
    $where_clauses[] = "expiry_date < CURDATE()";

$where_sql = implode(' AND ', $where_clauses);

// Pagination
$limit = 10;
$page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$total_res = $conn->query("SELECT COUNT(*) as total FROM inventory_items WHERE $where_sql");
$total_items = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

$result = $conn->query("SELECT *, DATEDIFF(expiry_date, CURDATE()) as days_to_expiry, (quantity <= 10 AND quantity > 0) as is_low_stock FROM inventory_items WHERE $where_sql ORDER BY name ASC LIMIT $limit OFFSET $offset");

include "index_layout.php";
?>

<div class="inventory-dashboard"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; padding: 30px 40px;">
    <?php
    $stats = [
        ['label' => 'Total Stock Items', 'val' => $result->num_rows, 'icon' => 'fa-boxes-stacked', 'clr' => '#00ACB1'],
        ['label' => 'Low Stock Alert', 'val' => 0, 'icon' => 'fa-triangle-exclamation', 'clr' => '#f39c12'],
        ['label' => 'Expired Items', 'val' => 0, 'icon' => 'fa-calendar-xmark', 'clr' => '#e74c3c'],
        ['label' => 'Out of Stock', 'val' => 0, 'icon' => 'fa-circle-xmark', 'clr' => '#95a5a6']
    ];
    // Re-verify totals
    $res_stats = $conn->query("SELECT 
        SUM(quantity <= 10 AND quantity > 0) as low, 
        SUM(quantity = 0) as out_of,
        SUM(expiry_date < CURDATE()) as exp 
        FROM inventory_items WHERE is_archived = 0");
    if ($s = $res_stats->fetch_assoc()) {
        $stats[1]['val'] = $s['low'] ?: 0;
        $stats[2]['val'] = $s['exp'] ?: 0;
        $stats[3]['val'] = $s['out_of'] ?: 0;
    }
    ?>
    <?php foreach ($stats as $st): ?>
        <?php
        $filterUrl = 'inventory.php';
        if ($st['label'] === 'Low Stock Alert')
            $filterUrl .= '?status=low_stock';
        elseif ($st['label'] === 'Expired Items')
            $filterUrl .= '?status=expired';
        elseif ($st['label'] === 'Out of Stock')
            $filterUrl .= '?status=out_of_stock';
        ?>
        <div class="stat-glass-card" style="--card-clr: <?= $st['clr'] ?>; cursor: pointer;"
            onclick="window.location.href='<?= $filterUrl ?>'">
            <div class="glass-icon"><i class="fa-solid <?= $st['icon'] ?>"></i></div>
            <div class="glass-text">
                <h3><?= number_format($st['val']) ?></h3>
                <p><?= $st['label'] ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<section class="controls-bar"
    style="padding: 15px 40px; display: flex; align-items: center; gap: 15px; background: #fff; border-bottom: 1px solid #f0f0f0;">
    <!-- Unified Filter/Search Group -->
    <form method="GET" style="display: flex; align-items: center; gap: 10px; flex: 1;">
        <div class="search-box-unified"
            style="display:flex; align-items:center; border: 2px solid #f0f0f0; border-radius: 12px; padding: 0 15px; height: 45px; flex: 1; background: #fbfbfc; transition: 0.3s;">
            <i class="fa fa-search" style="color: #b2bec3;"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search items..."
                style="border:none; outline:none; width: 100%; background:transparent; font-size: 0.95rem; padding-left: 10px; font-weight: 600;">
        </div>
        <select name="category" onchange="this.form.submit()"
            style="height: 45px; border-radius: 12px; border: 2px solid #f0f0f0; padding: 0 15px; background: #fff; font-weight: 700; color: #636e72; outline: none; cursor: pointer;">
            <option value="">All Categories</option>
            <option value="Medicine" <?= $category_filter == 'Medicine' ? 'selected' : '' ?>>Medicine</option>
            <option value="Medical Supply" <?= $category_filter == 'Medical Supply' ? 'selected' : '' ?>>Medical Supply
            </option>
            <option value="Equipment" <?= $category_filter == 'Equipment' ? 'selected' : '' ?>>Equipment</option>
        </select>
    </form>

    <!-- Unified Button Group -->
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="drug_log.php" class="btn-premium-action">
            <i class="fa-solid fa-file-waveform"></i> DRUG LOG
        </a>

        <div class="dropdown">
            <button class="btn-clean" onclick="toggleDropdown(event)"
                style="height: 45px; border: 2px solid #f0f0f0; background: #fff; border-radius: 12px; padding: 0 15px; font-weight: 800; color: #636e72; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <i class="fa-solid fa-download"></i> DOWNLOAD <i class="fa-solid fa-caret-down"></i>
            </button>
            <div class="dropdown-content"
                style="border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.1); border: 1px solid #f0f0f0; right: 0;">
                <a href="export_pdf.php?type=inventory" style="padding: 12px 20px; font-weight: 700; color: #333;"><i
                        class="fa-solid fa-file-pdf" style="color:#e74c3c"></i> PDF Report</a>
                <a href="export_xlsx.php?type=inventory" style="padding: 12px 20px; font-weight: 700; color: #333;"><i
                        class="fa-solid fa-file-excel" style="color:#2ecc71"></i> Excel Sheet</a>
            </div>
        </div>

        <button onclick="document.getElementById('addItemModal').style.display='flex'" class="btn-add-item"
            style="height: 45px; background: #00ACB1; color: white; border: none; border-radius: 12px; padding: 0 20px; font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; cursor: pointer; white-space: nowrap; box-shadow: 0 4px 12px rgba(0, 172, 177, 0.2);">
            <i class="fa fa-plus-circle"></i> ADD NEW ITEM
        </button>
    </div>
</section>

<div class="table-container" style="padding: 0 40px; margin-top: 10px;">
    <table style="width: 100%; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
        <thead>
            <tr style="background: linear-gradient(to right, #00ACB1, #00d4aa); color: white;">
                <th style="padding: 18px; text-align: left;">Item Name</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Unit</th>
                <th>Expiry Date</th>
                <th>Status</th>
                <th style="text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $status = 'status-ok';
                    $stext = 'Available';
                    if ($row['quantity'] == 0) {
                        $status = 'status-out';
                        $stext = 'Out of Stock';
                    } elseif ($row['expiry_date'] && $row['days_to_expiry'] < 0) {
                        $status = 'status-exp';
                        $stext = 'Expired';
                    } elseif ($row['is_low_stock']) {
                        $status = 'status-low';
                        $stext = 'Low Stock';
                    }
                    ?>
                    <tr>
                        <td class="item-name-cell">
                            <span class="main-name"><?= htmlspecialchars($row['name']) ?></span>
                            <span class="sub-desc"><?= htmlspecialchars($row['description']) ?: 'No extra details' ?></span>
                        </td>
                        <td><span style="font-weight: 700; color: #636e72;"><?= $row['category'] ?></span></td>
                        <td class="qty-bold"><?= $row['quantity'] ?></td>
                        <td style="color: #636e72; font-weight: 600;"><?= htmlspecialchars($row['unit']) ?></td>
                        <td style="font-weight: 600;">
                            <?= $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '<span style="color:#dfe6e9">N/A</span>' ?>
                        </td>
                        <td><span class="status-badge <?= $status ?>"><?= $stext ?></span></td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <button
                                    onclick="openStockModal(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>', <?= $row['quantity'] ?>)"
                                    class="action-btn"
                                    style="background: rgba(0, 172, 177, 0.1); color: #00ACB1; border:none; width:35px; height:35px; border-radius:8px; cursor:pointer;"
                                    title="In/Out"><i class="fa fa-boxes-packing"></i></button>
                                <button onclick="confirmArchive(<?= $row['id'] ?>)" class="action-btn"
                                    style="background: rgba(231, 76, 60, 0.1); color: #e74c3c; border:none; width:35px; height:35px; border-radius:8px; cursor:pointer;"
                                    title="Archive"><i class="fa fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="padding: 50px; text-align: center; color: #999;">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container" style="padding: 30px 0; display: flex; justify-content: center; gap: 10px;">
            <?php
            $queryString = $_GET;
            unset($queryString['p']);
            $baseUri = 'inventory.php?' . http_build_query($queryString) . '&p=';
            ?>

            <?php if ($page > 1): ?>
                <a href="<?= $baseUri . ($page - 1) ?>" class="page-btn-neo"><i class="fa fa-chevron-left"></i> Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?= $baseUri . $i ?>" class="page-btn-neo <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $baseUri . ($page + 1) ?>" class="page-btn-neo">Next <i class="fa fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Modal: Add Item -->
<div id="addItemModal" class="modal-overlay" style="display: none;">
    <div class="modal-card" style="width: 500px;">
        <div class="modal-header"
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h2 style="margin: 0; color: #00ACB1;"><i class="fa fa-plus-circle"></i> Add New Item</h2>
            <button class="close-btn" onclick="document.getElementById('addItemModal').style.display='none'"
                style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="action" value="add_item">
            <div style="padding: 20px 0; display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group"><label style="font-weight:bold; display:block; margin-bottom:5px;">Item Name
                        <span style="color:red">*</span></label><input type="text" name="name" required
                        style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;"></div>
                <div class="form-group"><label
                        style="font-weight:bold; display:block; margin-bottom:5px;">Category</label><select
                        name="category" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;">
                        <option>Medicine</option>
                        <option>Medical Supply</option>
                        <option>Equipment</option>
                    </select></div>
                <div class="form-group"><label
                        style="font-weight:bold; display:block; margin-bottom:5px;">Description</label><input
                        type="text" name="description"
                        style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;"></div>
                <div style="display: flex; gap: 10px;">
                    <div style="flex:1;"><label
                            style="font-weight:bold; display:block; margin-bottom:5px;">Quantity</label><input
                            type="number" name="quantity" value="0"
                            style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;"></div>
                    <div style="flex:1;"><label
                            style="font-weight:bold; display:block; margin-bottom:5px;">Unit</label><input type="text"
                            name="unit" placeholder="pcs/bot"
                            style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;"></div>
                </div>
                <div class="form-group"><label style="font-weight:bold; display:block; margin-bottom:5px;">Expiry
                        Date</label><input type="date" name="expiry_date"
                        style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;"></div>
            </div>
            <div
                style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #eee;">
                <button type="button" onclick="document.getElementById('addItemModal').style.display='none'"
                    style="padding: 10px 20px; border-radius:8px; border: 1px solid #ddd; background: #f8f9fa; cursor: pointer;">Cancel</button>
                <button type="submit"
                    style="padding: 10px 25px; border-radius:8px; border: none; background: #00ACB1; color: white; font-weight: bold; cursor: pointer;">Add
                    Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Stock -->
<div id="stockModal" class="modal-overlay" style="display: none;">
    <div class="modal-card" style="width: 450px;">
        <div class="modal-header"
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h2 id="stockTitle" style="margin: 0; color: #00ACB1;">Update Stock</h2>
            <button class="close-btn" onclick="document.getElementById('stockModal').style.display='none'"
                style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="action" value="update_stock">
            <input type="hidden" name="item_id" id="stockItemId">
            <div style="padding: 20px 0; display: flex; flex-direction: column; gap: 15px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 0.9rem; color: #666;">Current Stock Level</div>
                    <div id="currentStockDisplay" style="font-size: 2rem; font-weight: 800; color: #00ACB1;">0</div>
                </div>
                <div>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Transaction Type</label>
                    <select name="type" required
                        style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;">
                        <option value="Stock In">Stock In (Add)</option>
                        <option value="Stock Out">Stock Out (Remove)</option>
                        <option value="Dispensed">Dispensed (Usage)</option>
                        <option value="Adjustment">Adjustment</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Quantity</label>
                    <input type="number" name="quantity" required min="1"
                        style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;">
                </div>
                <div>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Remarks</label>
                    <input type="text" name="remarks" placeholder="Optional notes"
                        style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;">
                </div>
            </div>
            <div
                style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid #eee;">
                <button type="button" onclick="document.getElementById('stockModal').style.display='none'"
                    style="padding: 12px 25px; border-radius:10px; border: 1px solid #ddd; background: #fff; cursor: pointer;">Close</button>
                <button type="submit"
                    style="padding: 12px 30px; border-radius:10px; border: none; background: #00ACB1; color: white; font-weight: 800; cursor: pointer;">Save
                    Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleDropdown(e) {
        e.stopPropagation();
        const content = document.querySelector('.dropdown-content');
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    }
    window.onclick = () => {
        if (document.querySelector('.dropdown-content')) document.querySelector('.dropdown-content').style.display = 'none';
    }

    function openStockModal(id, name, qty) {
        document.getElementById('stockItemId').value = id;
        document.getElementById('stockTitle').innerText = name;
        document.getElementById('currentStockDisplay').innerText = qty;
        document.getElementById('stockModal').style.display = 'flex';
    }

    function confirmArchive(id) {
        Swal.fire({
            title: 'Archive Item?',
            text: 'It will be hidden from the active inventory.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Yes, Archive it'
        }).then(r => { if (r.isConfirmed) window.location.href = `inventory.php?archive_id=${id}`; });
    }
</script>

<style>
    /* Premium Cards in One Line */
    .inventory-dashboard {
        display: grid;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 15px !important;
        padding: 20px 40px !important;
    }

    .stat-glass-card {
        background: white;
        border-radius: 15px;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 2px solid #f8f9fa;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        transition: 0.3s;
    }

    .glass-icon {
        width: 45px;
        height: 45px;
        background: var(--card-clr);
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .glass-text h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 800;
        color: #1a1a1a;
    }

    .glass-text p {
        margin: 0;
        font-size: 0.75rem;
        font-weight: 700;
        color: #7f8c8d;
        text-transform: uppercase;
    }

    /* Max Readability Style */
    .table-container {
        margin-top: 25px;
        padding: 0 40px;
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    thead {
        background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%);
        color: white;
    }

    th {
        padding: 20px 15px;
        font-weight: 800;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-align: center;
    }

    td {
        padding: 20px 15px;
        border-bottom: 1px solid #f8f9fa;
        text-align: center;
        font-size: 1rem;
        color: #2d3436;
        font-family: 'Outfit', sans-serif;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    tbody tr:hover {
        background-color: #fcfdfe;
    }

    .status-badge {
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-ok {
        background: #e3fcfd;
        color: #008f94;
    }

    .status-low {
        background: #fff9e6;
        color: #f39c12;
    }

    .status-exp {
        background: #fff1f0;
        color: #e74c3c;
    }

    .status-out {
        background: #f1f2f6;
        color: #a4b0be;
    }

    .item-name-cell {
        text-align: left !important;
    }

    .item-name-cell .main-name {
        font-weight: 800;
        color: #00ACB1;
        font-size: 1.1rem;
        display: block;
    }

    .item-name-cell .sub-desc {
        font-size: 0.85rem;
        color: #b2bec3;
        font-weight: 400;
        margin-top: 4px;
    }

    .qty-bold {
        font-weight: 800;
        font-size: 1.2rem;
    }

    /* Premium Action Button */
    .btn-premium-action {
        height: 42px;
        background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%);
        color: white;
        padding: 0 30px;
        border-radius: 50px;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        font-weight: 800;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        box-shadow: 0 8px 20px rgba(0, 172, 177, 0.3);
        border: 2px solid white;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-premium-action:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 25px rgba(0, 172, 177, 0.4);
        filter: brightness(1.1);
        color: white;
    }

    /* Pagination Neo Style */
    .page-btn-neo {
        background: white;
        padding: 10px 18px;
        border-radius: 12px;
        color: #2d3436;
        text-decoration: none;
        font-weight: 800;
        font-size: 0.9rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        border: 2px solid #f0f0f0;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .page-btn-neo:hover {
        border-color: #00ACB1;
        color: #00ACB1;
        transform: translateY(-2px);
    }

    .page-btn-neo.active {
        background: #00ACB1;
        color: white;
        border-color: #00ACB1;
        box-shadow: 0 5px 15px rgba(0, 172, 177, 0.3);
    }

    /* Dark Mode Overrides for table content */
    body.dark-mode .inventory-wrapper {
        background: #18191a;
    }

    body.dark-mode table {
        background: #242526;
    }

    body.dark-mode tr[style*="background: white"] {
        background: #242526 !important;
        border-bottom-color: #3a3b3c !important;
    }

    body.dark-mode tr[style*="background: white"] td {
        color: #e4e6eb !important;
    }

    body.dark-mode div[style*="color: #00ACB1"] {
        color: #00d4aa !important;
    }

    body.dark-mode .modal-card {
        background: #242526 !important;
        color: #fff !important;
    }

    body.dark-mode .modal-header {
        border-bottom-color: #3a3b3c !important;
    }

    body.dark-mode input,
    body.dark-mode select {
        background: #3a3b3c !important;
        color: #fff !important;
        border-color: #4e4f50 !important;
    }

    body.dark-mode div[style*="background: #f8f9fa"] {
        background: #18191a !important;
    }
</style>