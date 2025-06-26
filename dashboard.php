<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hotel Bill Tracking System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Main Content */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: #718096;
            font-size: 1rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .card-hotel {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .card-employee {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .card-bill {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }

        .card-view {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }

        .card-report {
            background: linear-gradient(135deg, #9f7aea, #805ad5);
            color: white;
        }

        .card-settings {
            background: linear-gradient(135deg, #718096, #4a5568);
            color: white;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Recent Activity */
        .recent-activity {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-text {
            font-size: 0.9rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .main-container {
                padding: 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .user-section {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal styles for future use */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">HT</div>
                <div>
                    <div class="header-title">Hotel Bill Tracking System</div>
                    <div style="font-size: 0.8rem; opacity: 0.9;">Nestle Lanka Limited</div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name" id="userName">Account Assistant</div>
                    <div class="user-role" id="userRole">account_assistant</div>
                </div>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1 class="welcome-title">Welcome back!</h1>
            <p class="welcome-subtitle">Manage hotel bills and track promotional crew expenses efficiently.</p>
        </section>

        <!-- Stats Overview -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="totalBills">-</div>
                <div class="stat-label">Total Bills</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalEmployees">-</div>
                <div class="stat-label">Active Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalHotels">-</div>
                <div class="stat-label">Registered Hotels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="monthlyAmount">-</div>
                <div class="stat-label">This Month (LKR)</div>
            </div>
        </section>

        <!-- Dashboard Actions -->
        <section class="dashboard-grid">
            <div class="dashboard-card" onclick="navigateTo('hotels/register.php')">
                <div class="card-icon card-hotel">🏨</div>
                <h3 class="card-title">Register Hotel</h3>
                <p class="card-description">Add new hotels to the system and manage hotel information including rates and contact details.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('employees/register.php')">
                <div class="card-icon card-employee">👥</div>
                <h3 class="card-title">Register Employee</h3>
                <p class="card-description">Add new crew members and promotional staff to the employee database.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('bills/add.php')">
                <div class="card-icon card-bill">📋</div>
                <h3 class="card-title">Add New Bill</h3>
                <p class="card-description">Enter hotel bills submitted by propagandists with room assignments and additional charges.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('bills/view.php')">
                <div class="card-icon card-view">👁️</div>
                <h3 class="card-title">View Bills</h3>
                <p class="card-description">Review, search, and manage all hotel bills in the system with detailed filtering options.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('reports/index.php')">
                <div class="card-icon card-report">📊</div>
                <h3 class="card-title">Reports</h3>
                <p class="card-description">Generate detailed reports on expenses, employee stays, and hotel usage patterns.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('settings/index.php')">
                <div class="card-icon card-settings">⚙️</div>
                <h3 class="card-title">Settings</h3>
                <p class="card-description">Manage system settings, user accounts, and configure hotel rates.</p>
            </div>
        </section>

        <!-- Recent Activity -->
        <section class="recent-activity">
            <h2 class="section-title">
                <span>📋</span>
                Recent Activity
            </h2>
            <ul class="activity-list" id="activityList">
                <li class="activity-item">
                    <span class="activity-text">Loading recent activities...</span>
                    <span class="activity-time"><div class="loading"></div></span>
                </li>
            </ul>
        </section>
    </main>

    <script>
        // Navigation function
        function navigateTo(page) {
            // Add loading state
            event.target.style.opacity = '0.7';
            event.target.style.pointerEvents = 'none';
            
            // Simulate navigation (replace with actual page navigation)
            setTimeout(() => {
                window.location.href = page;
            }, 300);
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                // Add loading state
                document.querySelector('.logout-btn').innerHTML = '<div class="loading"></div>';
                
                // Simulate logout process
                setTimeout(() => {
                    window.location.href = 'logout.php';
                }, 1000);
            }
        }

        // Load dashboard statistics
        async function loadDashboardStats() {
            try {
                const response = await fetch('api/dashboard_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalBills').textContent = data.stats.total_bills || '0';
                    document.getElementById('totalEmployees').textContent = data.stats.total_employees || '0';
                    document.getElementById('totalHotels').textContent = data.stats.total_hotels || '0';
                    document.getElementById('monthlyAmount').textContent = formatCurrency(data.stats.monthly_amount || 0);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
                // Set default values on error
                document.getElementById('totalBills').textContent = '0';
                document.getElementById('totalEmployees').textContent = '0';
                document.getElementById('totalHotels').textContent = '0';
                document.getElementById('monthlyAmount').textContent = 'LKR 0';
            }
        }

        // Load recent activity
        async function loadRecentActivity() {
            try {
                const response = await fetch('api/recent_activity.php');
                const data = await response.json();
                
                const activityList = document.getElementById('activityList');
                
                if (data.success && data.activities.length > 0) {
                    activityList.innerHTML = data.activities.map(activity => `
                        <li class="activity-item">
                            <span class="activity-text">${activity.description}</span>
                            <span class="activity-time">${formatTime(activity.created_at)}</span>
                        </li>
                    `).join('');
                } else {
                    activityList.innerHTML = `
                        <li class="activity-item">
                            <span class="activity-text">No recent activities found</span>
                            <span class="activity-time">-</span>
                        </li>
                    `;
                }
            } catch (error) {
                console.error('Error loading activities:', error);
                document.getElementById('activityList').innerHTML = `
                    <li class="activity-item">
                        <span class="activity-text">Unable to load recent activities</span>
                        <span class="activity-time">-</span>
                    </li>
                `;
            }
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-LK', {
                style: 'currency',
                currency: 'LKR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }

        // Format time
        function formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString('en-LK');
        }

        // Load user information
        function loadUserInfo() {
            // This would typically fetch from session/API
            // For now, using placeholder data
            const userData = {
                name: 'Priyanka Silva',
                role: 'Account Assistant',
                email: 'accounts@nestle.lk'
            };
            
            document.getElementById('userName').textContent = userData.name;
            document.getElementById('userRole').textContent = userData.role;
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadUserInfo();
            loadDashboardStats();
            loadRecentActivity();
            
            // Refresh stats every 5 minutes
            setInterval(() => {
                loadDashboardStats();
                loadRecentActivity();
            }, 300000);
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        navigateTo('hotels/register.php');
                        break;
                    case '2':
                        e.preventDefault();
                        navigateTo('employees/register.php');
                        break;
                    case '3':
                        e.preventDefault();
                        navigateTo('bills/add.php');
                        break;
                    case '4':
                        e.preventDefault();
                        navigateTo('bills/view.php');
                        break;
                }
            }
        });

        // Add smooth transitions for better UX
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>