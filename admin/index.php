<?php
/**
 * Перенаправление на dashboard
 */
require_once __DIR__ . '/../includes/functions.php';
redirect(url('admin/dashboard.php'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <?php echo generate_meta_tags('Админ-панель'); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
        }
        
        /* Новый дизайн в темной теме */
        .header {
            background: #1e293b;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #334155;
        }
        
        .header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-actions {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .btn-logout {
            padding: 0.75rem 1.5rem;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-logout:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .tabs {
            background: #1e293b;
            border-radius: 0.75rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
            border: 1px solid #334155;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 2px solid #334155;
            overflow-x: auto;
            background: #0f172a;
        }
        
        .tab-btn {
            padding: 1.25rem 2rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #94a3b8;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .tab-btn:hover {
            color: #e2e8f0;
            background: #1e293b;
        }
        
        .tab-btn.active {
            color: #60a5fa;
            border-bottom-color: #60a5fa;
            background: #1e293b;
        }
        
        .tab-content {
            padding: 2rem;
        }
        
        .tab-panel {
            display: none;
        }
        
        .tab-panel.active {
            display: block;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        
        .btn-primary {
            padding: 0.875rem 1.75rem;
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(96,165,250,0.4);
        }
        
        .btn-danger {
            padding: 0.875rem 1.75rem;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn-success {
            padding: 0.875rem 1.75rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .table-responsive {
            overflow-x: auto;
            background: #0f172a;
            border-radius: 0.5rem;
            border: 1px solid #334155;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        thead {
            background: #1e293b;
            border-bottom: 2px solid #334155;
        }
        
        th {
            text-align: left;
            padding: 1rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.75rem;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #334155;
            color: #e2e8f0;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #1e293b;
        }
        
        .badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .badge-live {
            background: #ef4444;
            color: white;
        }
        
        .badge-scheduled {
            background: #f59e0b;
            color: white;
        }
        
        .badge-finished {
            background: #6b7280;
            color: white;
        }
        
        .btn-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit, .btn-delete {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #3b82f6;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2563eb;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: #1e293b;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border: 1px solid #334155;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #334155;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #94a3b8;
            line-height: 1;
            transition: color 0.2s;
        }
        
        .modal-close:hover {
            color: #e2e8f0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #e2e8f0;
            font-size: 0.875rem;
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #334155;
            border-radius: 0.5rem;
            font-size: 1rem;
            background: #0f172a;
            color: #e2e8f0;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #60a5fa;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-secondary {
            padding: 0.875rem 1.75rem;
            background: #334155;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .alert-success {
            background: #10b981;
            color: white;
        }
        
        .alert-error {
            background: #ef4444;
            color: white;
        }
        
        .loading {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        
        .site-status-card {
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .site-status-card h3 {
            color: #f1f5f9;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .site-status-indicator {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .status-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-dot.open {
            background: #10b981;
        }
        
        .status-dot.closed {
            background: #ef4444;
        }
        
        .status-text {
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .status-text.open {
            color: #10b981;
        }
        
        .status-text.closed {
            color: #ef4444;
        }
        
        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-responsive {
                font-size: 0.75rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>⚽ Админ-панель</h1>
        <div class="header-actions">
            <span class="admin-info">
                👤 <?php echo e($admin['username']); ?>
            </span>
            <a href="<?php echo url('admin/logout.php'); ?>" class="btn-logout">Выход</a>
        </div>
    </header>
    
    <main class="container">
        <div class="tabs">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="matches">Матчи</button>
                <button class="tab-btn" data-tab="site-status">Статус сайта</button>
            </div>
            
            <div class="tab-content">
                <div id="matches-panel" class="tab-panel active">
                    <div class="section-header">
                        <h2>Управление матчами</h2>
                        <button class="btn-primary" id="btnAddMatch">+ Новый матч</button>
                    </div>
                    
                    <div id="matchesAlert"></div>
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Матч</th>
                                    <th>Команды</th>
                                    <th>Лига</th>
                                    <th>Время</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="matchesBody">
                                <tr><td colspan="6" class="loading">Загрузка...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="site-status-panel" class="tab-panel">
                    <div class="section-header">
                        <h2>Управление статусом сайта</h2>
                    </div>
                    
                    <div id="siteStatusAlert"></div>
                    
                    <div class="site-status-card">
                        <h3>Текущий статус сайта</h3>
                        <div class="site-status-indicator">
                            <div class="status-dot" id="statusDot"></div>
                            <div class="status-text" id="statusText">Загрузка...</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="closeMessage">Сообщение при закрытии сайта</label>
                            <input type="text" class="form-input" id="closeMessage" placeholder="Сайт временно недоступен">
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button class="btn-danger" id="btnCloseSite">🔒 Закрыть сайт</button>
                            <button class="btn-success" id="btnOpenSite">✅ Открыть сайт</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Match Modal -->
    <div id="matchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="matchModalTitle">Новый матч</h3>
                <button class="modal-close" onclick="closeMatchModal()">&times;</button>
            </div>
            <form id="matchForm">
                <input type="hidden" id="matchId" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="matchTitle">Название матча *</label>
                    <input type="text" class="form-input" id="matchTitle" name="title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="teamA">Команда A *</label>
                    <input type="text" class="form-input" id="teamA" name="team_a" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="teamB">Команда B *</label>
                    <input type="text" class="form-input" id="teamB" name="team_b" required>
                </div>
                
                <!-- Добавлено поле выбора лиги -->
                <div class="form-group">
                    <label class="form-label" for="matchLeague">Лига</label>
                    <select class="form-select" id="matchLeague" name="league">
                        <option value="">Не выбрана</option>
                        <?php foreach (AVAILABLE_LEAGUES as $league): ?>
                            <option value="<?php echo e($league); ?>"><?php echo e($league); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="startTime">Время начала *</label>
                    <input type="datetime-local" class="form-input" id="startTime" name="start_time" required>
                </div>
                
                <!-- Добавлено поле времени окончания -->
                <div class="form-group">
                    <label class="form-label" for="endTime">Время окончания (автозакрытие)</label>
                    <input type="datetime-local" class="form-input" id="endTime" name="end_time">
                    <small style="color: #94a3b8; font-size: 0.75rem;">Матч автоматически закроется в указанное время</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="hlsUrl">HLS URL потока</label>
                    <input type="url" class="form-input" id="hlsUrl" name="hls_url" placeholder="https://example.com/stream.m3u8">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="coverImage">URL обложки</label>
                    <input type="url" class="form-input" id="coverImage" name="cover_image" placeholder="https://example.com/image.jpg">
                    <small style="color: #94a3b8; font-size: 0.75rem;">Изображение для превью (1280x720)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="matchStatus">Статус</label>
                    <select class="form-select" id="matchStatus" name="status">
                        <option value="scheduled">Запланирован</option>
                        <option value="live">В эфире</option>
                        <option value="finished">Завершён</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="telegramWidget">Telegram Discussion Widget</label>
                    <textarea class="form-textarea" id="telegramWidget" name="telegram_widget" placeholder='<script async src="https://telegram.org/js/telegram-widget.js?22" ...></script>'></textarea>
                    <small style="color: #94a3b8; font-size: 0.75rem;">Оставьте пустым для виджета по умолчанию</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Сохранить</button>
                    <button type="button" class="btn-secondary" onclick="closeMatchModal()">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const API_BASE = '<?php echo $api_url; ?>';
        const LEAGUES = <?php echo $leagues_json; ?>;

        console.log('[Admin] API Base URL:', API_BASE);
        
        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(tab + '-panel').classList.add('active');
                
                // Загружаем данные при переключении
                if (tab === 'matches') {
                    loadMatches();
                } else if (tab === 'site-status') {
                    loadSiteStatus();
                }
            });
        });
        
        // Matches Management
        async function loadMatches() {
            try {
                const response = await fetch(API_BASE + 'matches.php');
                const data = await response.json();
                
                if (!data.success) throw new Error(data.message);
                
                const tbody = document.getElementById('matchesBody');
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #94a3b8;">Матчи не найдены</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map(match => `
                    <tr>
                        <td><strong>${escapeHtml(match.title)}</strong></td>
                        <td>${escapeHtml(match.team_a)} vs ${escapeHtml(match.team_b)}</td>
                        <td>${match.league ? escapeHtml(match.league) : '<span style="color: #6b7280;">—</span>'}</td>
                        <td>
                            <div style="font-size: 0.875rem;">${formatDateTime(match.start_time)}</div>
                            ${match.end_time ? '<div style="font-size: 0.75rem; color: #94a3b8;">До: ' + formatDateTime(match.end_time) + '</div>' : ''}
                        </td>
                        <td>
                            <span class="badge badge-${match.status}">
                                ${match.status === 'live' ? 'В эфире' : match.status === 'scheduled' ? 'Запланирован' : 'Завершён'}
                            </span>
                        </td>
                        <td>
                            <div class="btn-actions">
                                <button class="btn-edit" onclick="editMatch(${match.id})">Изменить</button>
                                <button class="btn-delete" onclick="deleteMatch(${match.id}, '${escapeHtml(match.title)}')">Удалить</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showAlert('matchesAlert', error.message, 'error');
                console.error('[Admin] Load matches error:', error);
            }
        }
        
        document.getElementById('btnAddMatch').addEventListener('click', () => {
            document.getElementById('matchModalTitle').textContent = 'Новый матч';
            document.getElementById('matchForm').reset();
            document.getElementById('matchId').value = '';
            document.getElementById('matchModal').classList.add('active');
        });
        
        function closeMatchModal() {
            document.getElementById('matchModal').classList.remove('active');
        }
        
        async function editMatch(id) {
            try {
                const response = await fetch(API_BASE + 'matches.php?id=' + id);
                const data = await response.json();
                
                if (!data.success) throw new Error(data.message);
                
                const match = data.data;
                document.getElementById('matchModalTitle').textContent = 'Редактировать матч';
                document.getElementById('matchId').value = match.id;
                document.getElementById('matchTitle').value = match.title;
                document.getElementById('teamA').value = match.team_a;
                document.getElementById('teamB').value = match.team_b;
                document.getElementById('matchLeague').value = match.league || '';
                document.getElementById('startTime').value = match.start_time ? match.start_time.slice(0, 16) : '';
                document.getElementById('endTime').value = match.end_time ? match.end_time.slice(0, 16) : '';
                document.getElementById('hlsUrl').value = match.hls_url || '';
                document.getElementById('coverImage').value = match.cover_image || '';
                document.getElementById('matchStatus').value = match.status;
                document.getElementById('telegramWidget').value = match.telegram_widget || '';
                
                document.getElementById('matchModal').classList.add('active');
            } catch (error) {
                showAlert('matchesAlert', error.message, 'error');
                console.error('[Admin] Edit match error:', error);
            }
        }
        
        document.getElementById('matchForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            
            const matchId = data.id;
            delete data.id;
            
            try {
                const response = await fetch(API_BASE + 'matches.php', {
                    method: matchId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(matchId ? { ...data, id: matchId } : data)
                });
                
                const result = await response.json();
                
                if (!result.success) throw new Error(result.message);
                
                showAlert('matchesAlert', result.message, 'success');
                closeMatchModal();
                loadMatches();
            } catch (error) {
                showAlert('matchesAlert', error.message, 'error');
                console.error('[Admin] Save match error:', error);
            }
        });
        
        async function deleteMatch(id, title) {
            if (!confirm(`Удалить матч "${title}"?`)) return;
            
            try {
                const response = await fetch(API_BASE + 'matches.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                const result = await response.json();
                
                if (!result.success) throw new Error(result.message);
                
                showAlert('matchesAlert', result.message, 'success');
                loadMatches();
            } catch (error) {
                showAlert('matchesAlert', error.message, 'error');
                console.error('[Admin] Delete match error:', error);
            }
        }
        
        // Site Status Management
        async function loadSiteStatus() {
            try {
                const response = await fetch(API_BASE + 'site-status.php');
                const data = await response.json();
                
                if (!data.success) throw new Error(data.message);
                
                const isOpen = data.data.is_closed;
                const message = data.data.message;
                
                const statusDot = document.getElementById('statusDot');
                const statusText = document.getElementById('statusText');
                
                statusDot.className = 'status-dot ' + (isOpen ? 'open' : 'closed');
                statusText.className = 'status-text ' + (isOpen ? 'open' : 'closed');
                statusText.textContent = isOpen ? 'Сайт открыт' : 'Сайт закрыт';
                
                document.getElementById('closeMessage').value = message || '';
            } catch (error) {
                showAlert('siteStatusAlert', error.message, 'error');
                console.error('[Admin] Load site status error:', error);
            }
        }
        
        document.getElementById('btnCloseSite').addEventListener('click', async () => {
            const message = document.getElementById('closeMessage').value;
            
            if (!confirm('Закрыть сайт для пользователей?')) return;
            
            try {
                const response = await fetch(API_BASE + 'site-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ is_closed: false, message })
                });
                
                const result = await response.json();
                
                if (!result.success) throw new Error(result.message);
                
                showAlert('siteStatusAlert', 'Сайт закрыт', 'success');
                loadSiteStatus();
            } catch (error) {
                showAlert('siteStatusAlert', error.message, 'error');
            }
        });
        
        document.getElementById('btnOpenSite').addEventListener('click', async () => {
            if (!confirm('Открыть сайт для пользователей?')) return;
            
            try {
                const response = await fetch(API_BASE + 'site-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ is_closed: true })
                });
                
                const result = await response.json();
                
                if (!result.success) throw new Error(result.message);
                
                showAlert('siteStatusAlert', 'Сайт открыт', 'success');
                loadSiteStatus();
            } catch (error) {
                showAlert('siteStatusAlert', error.message, 'error');
            }
        });
        
        // Utility functions
        function showAlert(elementId, message, type) {
            const alertEl = document.getElementById(elementId);
            alertEl.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => alertEl.innerHTML = '', 5000);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleString('ru-RU', { 
                day: '2-digit', 
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        
        // Initial load
        loadMatches();
        
        // Автообновление статусов матчей каждые 60 секунд
        setInterval(() => {
            fetch(API_BASE + 'auto-update-matches.php')
                .then(res => res.json())
                .then(data => {
                    if (data.updated && data.updated > 0) {
                        console.log('[Admin] Автообновление: обновлено матчей -', data.updated);
                        loadMatches();
                    }
                })
                .catch(err => {
                    console.error('[Admin] Ошибка автообновления:', err);
                });
        }, 60000);
    </script>
</body>
</html>
