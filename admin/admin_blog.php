<?php
require_once __DIR__ . '/strict_admin.php';


// --- INTERNAL API LOGIC ---
$action = $_REQUEST['action'] ?? '';

if (!empty($action)) {
    header('Content-Type: application/json');
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'getBlogs':
                $stmt = $pdo->query("SELECT * FROM blogs ORDER BY created_at DESC");
                echo json_encode(['success' => true, 'blogs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'saveBlog':
                $title = $input['title'] ?? '';
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                $sql = "INSERT INTO blogs (title, slug, image_url, video_url, content) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            title = VALUES(title),
                            image_url = VALUES(image_url),
                            video_url = VALUES(video_url),
                            content = VALUES(content)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $title,
                    $slug,
                    $input['image_url'] ?? '',
                    $input['video_url'] ?? '',
                    $input['content'] ?? ''
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'deleteBlog':
                $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
                $stmt->execute([$input['id']]);
                echo json_encode(['success' => true]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #F1F5F9; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); }
        .modal-sheet { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
        .modal-sheet.active { transform: translateY(0); }
    </style>
</head>
<body class="pb-20">
    <header class="sticky top-0 z-40 glass border-b border-white/20 px-4 py-4">
        <div class="max-w-md mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-xl font-800 text-slate-900 tracking-tight">Blog Center</h1>
                <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest">Article & Videos</p>
            </div>
            <button onclick="openBlogModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl text-[11px] font-bold shadow-lg">
                <i class="fa-solid fa-plus mr-1"></i> NEW POST
            </button>
        </div>
    </header>

    <main class="max-w-md mx-auto p-4 space-y-4" id="blogList">
        <!-- Blogs will be loaded here -->
    </main>

    <!-- Blog Modal -->
    <div id="blogModal" class="fixed inset-0 z-[50] hidden">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" onclick="closeBlogModal()"></div>
        <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-[32px] modal-sheet p-6 max-h-[95vh] overflow-y-auto">
            <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-6"></div>
            <form id="blogForm" class="space-y-4">
                <input type="hidden" id="blogId" name="id">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Title</label>
                    <input type="text" name="title" id="blogTitle" class="w-full mt-1 px-4 py-3 bg-slate-50 rounded-2xl font-semibold outline-none border border-slate-100" required>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Cover Image URL</label>
                    <input type="text" name="image_url" id="blogImage" class="w-full mt-1 px-4 py-3 bg-slate-50 rounded-2xl font-semibold outline-none border border-slate-100">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Video Link (YouTube/FB/Insta)</label>
                    <input type="text" name="video_url" id="blogVideo" placeholder="https://..." class="w-full mt-1 px-4 py-3 bg-slate-50 rounded-2xl font-semibold outline-none border border-slate-100">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Content / Article</label>
                    <textarea name="content" id="blogContent" rows="6" class="w-full mt-1 px-4 py-3 bg-slate-50 rounded-2xl font-semibold outline-none border border-slate-100"></textarea>
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl shadow-xl">PUBLISH ARTICLE</button>
            </form>
        </div>
    </div>

    <script>
        const API_URL = window.location.pathname;
        let allBlogs = [];

        async function fetchBlogs() {
            const res = await fetch(`${API_URL}?action=getBlogs`);
            const data = await res.json();
            if(data.success) {
                allBlogs = data.blogs;
                renderBlogs();
            }
        }

        function renderBlogs() {
            const list = document.getElementById('blogList');
            if(allBlogs.length === 0) {
                list.innerHTML = `<div class="py-20 text-center text-slate-400">No blog posts yet</div>`;
                return;
            }
            list.innerHTML = allBlogs.map(b => `
                <div class="bg-white p-4 rounded-[28px] shadow-sm border border-slate-100 space-y-3">
                    ${b.image_url ? `<img src="${b.image_url}" class="w-full h-40 object-cover rounded-2xl">` : ''}
                    <div>
                        <h3 class="font-800 text-slate-900 text-sm">${b.title}</h3>
                        <p class="text-[10px] text-slate-400 mt-1">${new Date(b.created_at).toLocaleDateString()}</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="editBlog(${b.id})" class="flex-1 bg-blue-50 text-blue-600 py-2 rounded-xl text-[10px] font-black uppercase">Edit</button>
                        <button onclick="deleteBlog(${b.id})" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            `).join('');
        }

        function openBlogModal() {
            document.getElementById('blogForm').reset();
            document.getElementById('blogId').value = "";
            const modal = document.getElementById('blogModal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.querySelector('.modal-sheet').classList.add('active'), 10);
        }

        function closeBlogModal() {
            const modal = document.getElementById('blogModal');
            modal.querySelector('.modal-sheet').classList.remove('active');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        function editBlog(id) {
            const b = allBlogs.find(x => x.id == id);
            openBlogModal();
            document.getElementById('blogId').value = b.id;
            document.getElementById('blogTitle').value = b.title;
            document.getElementById('blogImage').value = b.image_url;
            document.getElementById('blogVideo').value = b.video_url;
            document.getElementById('blogContent').value = b.content;
        }

        async function deleteBlog(id) {
            if(!confirm("Delete this post?")) return;
            const res = await fetch(`${API_URL}?action=deleteBlog`, {
                method: 'POST',
                body: JSON.stringify({ id: id })
            });
            if((await res.json()).success) fetchBlogs();
        }

        document.getElementById('blogForm').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target).entries());
            const res = await fetch(`${API_URL}?action=saveBlog`, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            if((await res.json()).success) {
                closeBlogModal();
                fetchBlogs();
            }
        };

        fetchBlogs();
    </script>
</body>
</html>
