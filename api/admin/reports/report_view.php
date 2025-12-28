<?php
// 1. DB Connection FIRST (আগে ডাটাবেস কানেক্ট করুন)
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// 2. FORCE HTML HEADER (DB ফাইলের JSON হেডার বাতিল করার জন্য এটি নিচে দিতে হবে)
header("Content-Type: text/html; charset=UTF-8");

// 3. Fetch Data
$sql = "SELECT o.id, u.name as customer, s.name as service, p.name as provider, o.total_price, o.status, o.created_at
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN users p ON o.provider_id = p.id
        LEFT JOIN services s ON o.service_id = s.id
        ORDER BY o.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Report Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4F46E5;
            --secondary: #10B981;
            --bg: #F3F4F6;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            margin: 0;
            padding: 40px 20px;
            color: var(--text-dark);
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Header Section */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--white);
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .page-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h2 i {
            color: var(--primary);
            background: #EEF2FF;
            padding: 10px;
            border-radius: 10px;
            font-size: 20px;
        }

        /* Download Button */
        .btn-download {
            background-color: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .btn-download:hover {
            background-color: #4338CA;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
        }

        /* Table Card */
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        thead {
            background-color: #F9FAFB;
            border-bottom: 1px solid #E5E7EB;
        }

        th {
            padding: 16px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-light);
            letter-spacing: 0.05em;
        }

        td {
            padding: 16px 24px;
            border-bottom: 1px solid #F3F4F6;
            font-size: 14px;
            color: var(--text-dark);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background-color: #F9FAFB;
        }

        /* Styles for Specific Columns */
        .order-id {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: var(--primary);
        }

        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .customer-avatar {
            width: 32px;
            height: 32px;
            background: #E0E7FF;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .price {
            font-weight: 600;
        }

        /* Status Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-completed { background-color: #D1FAE5; color: #065F46; }
        .status-pending { background-color: #FEF3C7; color: #92400E; }
        .status-assigned { background-color: #DBEAFE; color: #1E40AF; }
        .status-cancelled { background-color: #FEE2E2; color: #991B1B; }

        .date-col {
            color: var(--text-light);
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-light);
        }
        .empty-state i {
            font-size: 40px;
            margin-bottom: 15px;
            color: #D1D5DB;
        }

    </style>
</head>
<body>

<div class="container">
    
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Order Report Dashboard</h2>
        <a href="export_orders.php" class="btn-download">
            <i class="fas fa-file-csv"></i> Download CSV Report
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Provider</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><span class="order-id">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                
                                <td>
                                    <div class="customer-info">
                                        <div class="customer-avatar">
                                            <?php echo strtoupper(substr($row['customer'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($row['customer']); ?>
                                    </div>
                                </td>
                                
                                <td><?php echo htmlspecialchars($row['service']); ?></td>
                                
                                <td>
                                    <?php if($row['provider']): ?>
                                        <i class="fas fa-user-cog" style="color: #9CA3AF; margin-right:5px;"></i>
                                        <?php echo htmlspecialchars($row['provider']); ?>
                                    <?php else: ?>
                                        <span style="color: #9CA3AF; font-style: italic;">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="price"><?php echo number_format($row['total_price'], 2); ?> ৳</td>
                                
                                <td>
                                    <span class="badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                
                                <td class="date-col">
                                    <i class="far fa-calendar-alt" style="margin-right:5px;"></i>
                                    <?php echo date("d M, Y", strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No orders found in the database.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
<?php $conn->close(); ?>