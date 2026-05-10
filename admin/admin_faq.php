<?php
require_once '../config.php';

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM faqs WHERE id = $id");
    header("Location: admin_faq.php?msg=Deleted");
    exit;
}

// --- HANDLE ADD/UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $game_id = !empty($_POST['game_id']) ? intval($_POST['game_id']) : NULL;
    $question = $conn->real_escape_string($_POST['question']);
    $answer = $conn->real_escape_string($_POST['answer']);
    $sort_order = intval($_POST['sort_order']);
    $status = intval($_POST['status']);

    if (!empty($id)) {
        // Update
        $stmt = $conn->prepare("UPDATE faqs SET game_id=?, question=?, answer=?, sort_order=?, status=? WHERE id=?");
        $stmt->bind_param("issiii", $game_id, $question, $answer, $sort_order, $status, $id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO faqs (game_id, question, answer, sort_order, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issii", $game_id, $question, $answer, $sort_order, $status);
    }
    
    $stmt->execute();
    header("Location: admin_faq.php?msg=Success");
    exit;
}

// Fetch all games for the dropdown
$games_list = $conn->query("SELECT id, title FROM games ORDER BY title ASC");

// Fetch all FAQs with game titles
$faqs = $conn->query("SELECT f.*, g.title as game_name FROM faqs f LEFT JOIN games g ON f.game_id = g.id ORDER BY f.sort_order ASC");
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FAQ Admin | Dark Mode</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; }
        
        @media (max-width: 768px) {
            .mobile-bottom-sheet {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                border-radius: 24px 24px 0 0;
                transform: translateY(100%);
                transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                max-height: 92vh;
                display: block !important;
                background-color: #1e293b;
                box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.5);
            }
            .mobile-bottom-sheet.active {
                transform: translateY(0);
            }
        }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        
        /* Glassmorphism for Desktop Header */
        .glass-header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(51, 65, 85, 0.5);
        }
    </style>
</head>
<body class="font-sans antialiased selection:bg-blue-500/30">

    <header class="sticky top-0 z-40 glass-header px-4 py-4 md:px-8">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-900/20">
                    <i class="fas fa-layer-group text-white"></i>
                </div>
                <div>
                    <h1 class="text-lg md:text-xl font-bold tracking-tight">FAQ <span class="text-blue-400 font-black">PRO</span></h1>
                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Control Center</span>
                </div>
            </div>
            <button onclick="openModal()" class="hidden md:flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold transition-all active:scale-95 shadow-lg shadow-blue-900/40">
                <i class="fas fa-plus text-xs"></i>
                <span>Add New</span>
            </button>
        </div>
    </header>

    <main class="max-w-5xl mx-auto p-4 md:p-8 pb-28 md:pb-12">
        
        <?php if(isset($_GET['msg'])): ?>
        <div class="mb-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-2xl text-blue-400 text-sm font-bold flex items-center gap-3">
            <i class="fas fa-circle-check"></i>
            Action Completed: <?= htmlspecialchars($_GET['msg']) ?>
        </div>
        <?php endif; ?>

        <div class="hidden md:block bg-slate-800/50 rounded-3xl border border-slate-700/50 overflow-hidden backdrop-blur-sm">
            <table class="w-full text-left">
                <thead class="bg-slate-900/50">
                    <tr>
                        <th class="p-5 text-[11px] font-black uppercase text-slate-500 tracking-widest">Context</th>
                        <th class="p-5 text-[11px] font-black uppercase text-slate-500 tracking-widest">Question</th>
                        <th class="p-5 text-[11px] font-black uppercase text-slate-500 tracking-widest text-center">Status</th>
                        <th class="p-5 text-[11px] font-black uppercase text-slate-500 tracking-widest text-center">Order</th>
                        <th class="p-5 text-[11px] font-black uppercase text-slate-500 tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    <?php while($row = $faqs->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider <?= $row['game_name'] ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-slate-700/50 text-slate-400' ?>">
                                <?= $row['game_name'] ? htmlspecialchars($row['game_name']) : 'General' ?>
                            </span>
                        </td>
                        <td class="p-5">
                            <div class="text-sm font-semibold text-slate-200"><?= htmlspecialchars($row['question']) ?></div>
                        </td>
                        <td class="p-5 text-center">
                            <span class="inline-flex h-2 w-2 rounded-full <?= $row['status'] ? 'bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.5)]' : 'bg-slate-600' ?>"></span>
                        </td>
                        <td class="p-5 text-center text-sm font-mono text-slate-500"><?= $row['sort_order'] ?></td>
                        <td class="p-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick='editFaq(<?= json_encode($row) ?>)' class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-700/50 text-slate-300 hover:bg-blue-600 hover:text-white transition-all"><i class="fas fa-edit text-xs"></i></button>
                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete permanently?')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-700/50 text-slate-300 hover:bg-red-500 hover:text-white transition-all"><i class="fas fa-trash text-xs"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-4">
            <?php $faqs->data_seek(0); while($row = $faqs->fetch_assoc()): ?>
            <div class="bg-slate-800 border border-slate-700/50 p-5 rounded-[2rem] active:scale-[0.98] transition-transform" onclick='editFaq(<?= json_encode($row) ?>)'>
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[10px] font-black uppercase tracking-widest <?= $row['game_name'] ? 'text-blue-400' : 'text-slate-500' ?>">
                        <?= $row['game_name'] ? htmlspecialchars($row['game_name']) : 'General' ?>
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono text-slate-600 italic">Pos: <?= $row['sort_order'] ?></span>
                        <div class="h-1.5 w-1.5 rounded-full <?= $row['status'] ? 'bg-emerald-400' : 'bg-slate-600' ?>"></div>
                    </div>
                </div>
                <h4 class="text-base font-bold text-slate-100 mb-2 leading-snug"><?= htmlspecialchars($row['question']) ?></h4>
                <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed opacity-80 mb-4"><?= htmlspecialchars($row['answer']) ?></p>
                <div class="flex items-center justify-between pt-4 border-t border-slate-700/50">
                    <button class="text-[11px] font-black uppercase tracking-widest text-blue-400">Modify</button>
                    <a href="?delete=<?= $row['id'] ?>" onclick="event.stopPropagation(); return confirm('Delete?')" class="text-[11px] font-black uppercase tracking-widest text-red-400/70">Remove</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </main>

    <button onclick="openModal()" class="md:hidden fixed bottom-8 right-6 w-16 h-16 bg-blue-600 text-white rounded-2xl shadow-2xl shadow-blue-900/60 flex items-center justify-center text-2xl z-30 active:scale-90 transition-transform border border-blue-400/30">
        <i class="fas fa-plus"></i>
    </button>

    <div id="faqModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md hidden flex items-center justify-center p-0 md:p-6 z-50 transition-all">
        <div class="mobile-bottom-sheet w-full max-w-lg md:rounded-[2.5rem] md:relative md:transform-none border-t md:border border-slate-700/50">
            
            <div class="md:hidden w-16 h-1.5 bg-slate-700 rounded-full mx-auto mt-4 mb-2"></div>
            
            <div class="px-8 py-6 flex justify-between items-center border-b border-slate-700/30">
                <div>
                    <h3 id="modalTitle" class="text-xl font-bold text-white">Create FAQ</h3>
                    <p class="text-xs text-slate-500 font-medium">Update help center database</p>
                </div>
                <button onclick="closeModal()" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-slate-700/30 text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
            </div>

            <form action="" method="POST" class="p-8 space-y-6 custom-scrollbar overflow-y-auto max-h-[75vh]">
                <input type="hidden" name="id" id="f_id">
                
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Target Application</label>
                    <div class="relative">
                        <select name="game_id" id="f_game_id" class="w-full bg-slate-900 border border-slate-700/50 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 outline-none appearance-none transition-all text-slate-200">
                            <option value="">Global / All Systems</option>
                            <?php $games_list->data_seek(0); while($g = $games_list->fetch_assoc()): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <i class="fas fa-angle-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-600 pointer-events-none"></i>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">The Question</label>
                    <input type="text" name="question" id="f_question" required placeholder="Type the question..." class="w-full bg-slate-900 border border-slate-700/50 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-blue-500/50 outline-none transition-all placeholder:text-slate-700 text-slate-200">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">The Answer</label>
                    <textarea name="answer" id="f_answer" rows="4" required placeholder="Write the resolution here..." class="w-full bg-slate-900 border border-slate-700/50 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-blue-500/50 outline-none transition-all placeholder:text-slate-700 text-slate-200"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Priority Order</label>
                        <input type="number" name="sort_order" id="f_sort_order" value="0" class="w-full bg-slate-900 border border-slate-700/50 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-blue-500/50 outline-none transition-all text-slate-200">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Initial Visibility</label>
                        <select name="status" id="f_status" class="w-full bg-slate-900 border border-slate-700/50 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-blue-500/50 outline-none appearance-none transition-all text-slate-200">
                            <option value="1">Live (Active)</option>
                            <option value="0">Draft (Hidden)</option>
                        </select>
                    </div>
                </div>

                <div class="pt-4 pb-8 md:pb-0">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-[1.5rem] font-black text-sm uppercase tracking-widest shadow-xl shadow-blue-900/20 transition-all active:scale-[0.98]">
                        Commit Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('faqModal');
        const sheet = modal.querySelector('.mobile-bottom-sheet');

        function openModal() {
            document.getElementById('modalTitle').innerText = "Create FAQ";
            document.getElementById('f_id').value = "";
            document.getElementById('f_question').value = "";
            document.getElementById('f_answer').value = "";
            document.getElementById('f_sort_order').value = "0";
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                sheet.classList.add('active');
            }, 50);
        }

        function closeModal() {
            sheet.classList.remove('active');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function editFaq(data) {
            document.getElementById('modalTitle').innerText = "Update FAQ";
            document.getElementById('f_id').value = data.id;
            document.getElementById('f_game_id').value = data.game_id || "";
            document.getElementById('f_question').value = data.question;
            document.getElementById('f_answer').value = data.answer;
            document.getElementById('f_sort_order').value = data.sort_order;
            document.getElementById('f_status').value = data.status;
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                sheet.classList.add('active');
            }, 50);
        }

        // Close modal on clicking outside the drawer
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>
</body>
</html>