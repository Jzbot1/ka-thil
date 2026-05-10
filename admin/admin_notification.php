<?php
require_once __DIR__ . '/strict_admin.php';

// --- DIRECTORY SETTINGS ---
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/notifications/'); 
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
define('DISPLAY_PATH', 'uploads/notifications/');

// --- INTERNAL API LOGIC ---
$action = $_REQUEST['action'] ?? '';

if (!empty($action)) {
    header('Content-Type: application/json');
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        
        switch($action) {
            case 'list':
                $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
                echo json_encode($stmt->fetchAll());
                break;

            case 'save':
                $data = json_decode(file_get_contents('php://input'), true);
                if (empty($data['id'])) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, image_url, video_url) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$data['title'], $data['message'], $data['image_url'], $data['video_url']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE notifications SET title=?, message=?, image_url=?, video_url=? WHERE id=?");
                    $stmt->execute([$data['title'], $data['message'], $data['image_url'], $data['video_url'], $data['id']]);
                }
                echo json_encode(['success' => true]);
                break;

            case 'delete':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                $stmt->execute([$data['id']]);
                echo json_encode(['success' => true]);
                break;

            case 'uploadImage': 
                if (isset($_FILES['image'])) {
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid() . '.' . $ext;
                    $targetPath = UPLOAD_DIR . $fileName;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        echo json_encode(['success' => true, 'filePath' => DISPLAY_PATH . $fileName]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Upload failed']);
                    }
                }
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #0f172a; }
        .glass-panel { background: white; border: 1px solid #e2e8f0; }
    </style>
</head>
<body class="pb-20">

    <header class="bg-white border-b border-slate-200 px-6 h-16 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <a href="../profile" class="text-slate-400 hover:text-slate-600 transition"><i class="fa-solid fa-arrow-left"></i></a>
            <h1 class="font-bold text-lg">Notifications</h1>
        </div>
        <button onclick="openModal()" class="bg-blue-600 text-white text-[10px] font-black px-4 py-2 rounded-xl uppercase tracking-widest shadow-lg shadow-blue-200">
            Create New
        </button>
    </header>

    <main class="max-w-2xl mx-auto p-4 space-y-4" id="notiList">
        <!-- List injected here -->
    </main>

    <!-- Modal -->
    <div id="notiModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute bottom-0 left-0 w-full bg-white rounded-t-[2.5rem] p-6 max-w-md mx-auto left-1/2 -translate-x-1/2 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="w-12 h-1.5 bg-slate-100 rounded-full mx-auto mb-6"></div>
            <h2 class="text-xl font-black mb-6" id="modalTitle">New Notification</h2>
            
            <form id="notiForm" class="space-y-4">
                <input type="hidden" id="notiId">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase mb-1 block">Title</label>
                    <input type="text" id="title" class="w-full bg-slate-50 border-0 rounded-2xl p-4 text-sm font-bold outline-none ring-blue-500/10 focus:ring-4" placeholder="Important Update">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase mb-1 block">Message</label>
                    <textarea id="message" rows="4" class="w-full bg-slate-50 border-0 rounded-2xl p-4 text-sm font-bold outline-none ring-blue-500/10 focus:ring-4" placeholder="Write details here..."></textarea>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase mb-1 block">Video Link (YouTube/FB)</label>
                    <input type="text" id="video_url" class="w-full bg-slate-50 border-0 rounded-2xl p-4 text-sm font-bold outline-none ring-blue-500/10 focus:ring-4" placeholder="https://...">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase mb-1 block">Image</label>
                    <div class="flex items-center gap-4">
                        <img id="imagePreview" src="https://placehold.co/100" class="w-16 h-16 rounded-xl object-cover border border-slate-100">
                        <div class="flex-1">
                            <input type="hidden" id="image_url">
                            <input type="file" id="imageUpload" class="hidden" accept="image/*">
                            <button type="button" onclick="document.getElementById('imageUpload').click()" class="bg-slate-100 text-slate-600 text-[10px] font-black px-4 py-2 rounded-lg uppercase tracking-widest">
                                Upload Photo
                            </button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 uppercase tracking-widest">
                    Save Notification
                </button>
            </form>
        </div>
    </div>

    <script>
        const API = 'admin_notification.php';
        let allNotis = [];

        async function loadNotis() {
            const res = await fetch(API + '?action=list');
            allNotis = await res.json();
            const list = document.getElementById('notiList');
            list.innerHTML = allNotis.map(n => `
                <div class="bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm relative group">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-slate-800">${n.title}</h3>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="editNoti(${n.id})" class="text-blue-500 p-2"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="deleteNoti(${n.id})" class="text-rose-500 p-2"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 line-clamp-2 mb-3">${n.message}</p>
                    ${n.image_url ? `<img src="../${n.image_url}" class="w-full h-32 object-cover rounded-2xl mb-2">` : ''}
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">${n.created_at}</p>
                </div>
            `).join('');
        }

        function openModal() {
            document.getElementById('notiForm').reset();
            document.getElementById('notiId').value = '';
            document.getElementById('imagePreview').src = 'https://placehold.co/100';
            document.getElementById('image_url').value = '';
            document.getElementById('modalTitle').innerText = 'New Notification';
            document.getElementById('notiModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('notiModal').classList.add('hidden');
        }

        function editNoti(id) {
            const n = allNotis.find(x => x.id == id);
            document.getElementById('notiId').value = n.id;
            document.getElementById('title').value = n.title;
            document.getElementById('message').value = n.message;
            document.getElementById('video_url').value = n.video_url || '';
            document.getElementById('image_url').value = n.image_url || '';
            document.getElementById('imagePreview').src = n.image_url ? '../' + n.image_url : 'https://placehold.co/100';
            document.getElementById('modalTitle').innerText = 'Edit Notification';
            document.getElementById('notiModal').classList.remove('hidden');
        }

        async function deleteNoti(id) {
            if(!confirm('Delete this?')) return;
            await fetch(API + '?action=delete', { method: 'POST', body: JSON.stringify({id}) });
            loadNotis();
        }

        document.getElementById('imageUpload').onchange = async function() {
            const fd = new FormData();
            fd.append('image', this.files[0]);
            const res = await fetch(API + '?action=uploadImage', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                document.getElementById('image_url').value = data.filePath;
                document.getElementById('imagePreview').src = '../' + data.filePath;
            }
        };

        document.getElementById('notiForm').onsubmit = async function(e) {
            e.preventDefault();
            const data = {
                id: document.getElementById('notiId').value,
                title: document.getElementById('title').value,
                message: document.getElementById('message').value,
                video_url: document.getElementById('video_url').value,
                image_url: document.getElementById('image_url').value
            };
            const res = await fetch(API + '?action=save', { method: 'POST', body: JSON.stringify(data) });
            const result = await res.json();
            if(result.success) {
                closeModal();
                loadNotis();
            }
        };

        loadNotis();
    </script>
</body>
</html>
