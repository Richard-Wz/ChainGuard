<?php
session_start();

// Prevent caching of the page
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Ensure the user is logged in and has 'admin' role
include('../php/auth_admin.php');

// Autoload MongoDB client
require '../vendor/autoload.php';

// Connect to MongoDB
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");

// Select the "drivers" collection
$driversCollection = $mongoClient->chainguard->drivers;

// Filter drivers based on search criteria
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$searchFilter = [];
if (!empty($searchQuery)) {
    $searchFilter = [
        '$or' => [
            ['fullName' => new MongoDB\BSON\Regex($searchQuery, 'i')],
            ['email' => new MongoDB\BSON\Regex($searchQuery, 'i')],
            ['licensePlate' => new MongoDB\BSON\Regex($searchQuery, 'i')]
        ]
    ];
}

// Setup pagination variables
$pageSize = 10;
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}
$offset = ($currentPage - 1) * $pageSize;

// Get total drivers count for pagination using the filter
$totalDrivers = $driversCollection->countDocuments($searchFilter);

// Retrieve paginated driver documents using the filter
$driversCursor = $driversCollection->find($searchFilter, [
    'skip'  => $offset,
    'limit' => $pageSize
]);
$drivers = iterator_to_array($driversCursor);

// Calculate total pages
$totalPages = ceil($totalDrivers / $pageSize);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>ChainGuard - User Account Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../css/admin_user_management.css" />
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Brand -->
        <div class="brand">
            <div class="brand-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            ChainGuard
        </div>

        <!-- Menu Items -->
        <div class="menu-item">
            <a href="../php/admin_dashboard.php">
                <div class="menu-icon">
                    <i class="fas fa-gauge"></i>
                </div>
                Dashboard
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/admin_logging.php">
                <div class="menu-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                Violation Logging
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/admin_record.php">
                <div class="menu-icon">
                    <i class="fas fa-history"></i>
                </div>
                Violation History
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/admin_appeal.php">
                <div class="menu-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                Appeals
            </a>
        </div>

        <div class="menu-item active">
            <a href="../php/admin_user_management.php">
                <div class="menu-icon">
                    <i class="fas fa-user"></i>
                </div>
                User Management
            </a>
        </div>

        <div class="menu-item">
            <a href="../php/logout.php">
                <div class="menu-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                Sign Out
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="hamburger-menu">
                <i class="fas fa-bars"></i>
            </div>
            <div class="page-title">User Management</div>
            <div class="header-actions">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- User Management Table -->
        <div class="user-management-container">
            <div class="filter-form">
                <form method="GET" action="admin_user_management.php">
                    <input type="text" name="search" id="search" placeholder="Search User" 
                        value="<?php echo htmlspecialchars($searchQuery); ?>" 
                        onchange="this.form.submit()">
                </form>
            </div>
            <table class="user-table">
                <thead>
                    <tr>
                        <th class="user-header">User</th>
                        <th>License</th>
                        <th class="action-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)) : ?>
                        <tr>
                            <td colspan="3">No users found.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($drivers as $driver) : ?>
                            <tr
                                data-id="<?php echo htmlspecialchars($driver['_id']); ?>"
                                data-fullname="<?php echo htmlspecialchars($driver['fullName']); ?>"
                                data-email="<?php echo htmlspecialchars($driver['email']); ?>"
                                data-contactnumber="<?php echo htmlspecialchars($driver['contactNumber']); ?>"
                                data-licenseplate="<?php echo htmlspecialchars($driver['licensePlate']); ?>"
                                data-address="<?php echo htmlspecialchars($driver['address']); ?>"
                                data-createdat="<?php echo htmlspecialchars($driver['createdAt']); ?>"
                                data-blockchainuserid="<?php echo htmlspecialchars($driver['blockchainUserID']); ?>"
                                data-blockchainclientid="<?php echo htmlspecialchars($driver['blockchainClientID']); ?>"
                                data-profileimage="<?php echo htmlspecialchars($driver['profileImage']); ?>"
                            >
                                <td>
                                    <div class="user-profile">
                                        <div class="user-profile-icon">
                                            <?php if ($driver && isset($driver['profileImage'])): ?>
                                                <img src="<?php echo htmlspecialchars($driver['profileImage']); ?>" alt="Profile Image">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($driver['fullName']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($driver['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-license">
                                        <?php echo htmlspecialchars($driver['licensePlate']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <button class="btn btn-edit" data-id="<?php echo htmlspecialchars($driver['_id']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-delete" data-id="<?php echo htmlspecialchars($driver['_id']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination Controls -->
            <div class="table-footer">
                <span>Showing <?php echo count($drivers); ?> of <?php echo $totalDrivers; ?> results</span>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $currentPage - 1; ?>"><button>Previous</button></a>
                    <?php endif; ?>

                    <?php
                    // Display page numbers 
                    for ($page = 1; $page <= $totalPages; $page++):
                        if ($page == $currentPage):
                    ?>
                            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page; ?>"><button class="active"><?php echo $page; ?></button></a>
                        <?php else: ?>
                            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page; ?>"><button><?php echo $page; ?></button></a>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $currentPage + 1; ?>"><button>Next</button></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pop up Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>User Details</h2>
            <div class="modal-details">
                <p><strong>Name:</strong> <span id="modalName"></span></p>
                <p><strong>User ID:</strong> <span id="modalUserID"></span></p>
                <p><strong>Wallet ID:</strong> <span id="modalWalletID"></span></p>
                <p><strong>Account Creation Date:</strong> <span id="modalCreatedAt"></span></p>
                <p><strong>Email:</strong> <span id="modalEmail"></span></p>
                <p><strong>Contact Number:</strong> <span id="modalContact"></span></p>
                <p><strong>License Plate:</strong> <span id="modalLicense"></span></p>
                <p><strong>Address:</strong> <span id="modalAddress"></span></p>
                <!-- <p><strong>Blockchain User ID:</strong> <span id="modalClientID"></span></p> -->
                <div id="modalImageContainer">
                    <img id="modalImage" src="" alt="Profile Image">
                </div>
            </div>
        </div>
    </div>
    <script src="../scripts/admin_user_management.js"></script>
</body>
</html>