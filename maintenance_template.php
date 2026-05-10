<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode – <?= htmlspecialchars($setting['store_name'] ?? 'JZ Store') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); 
            background-attachment: fixed; 
            color: #0f172a; 
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.4); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        }
        .font-dynapuff { font-family: 'DynaPuff', cursive; }
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        .bg-blob { position: absolute; width: 500px; height: 500px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%); filter: blur(80px); border-radius: 50%; animation: move 20s infinite alternate; z-index: -1; }
        @keyframes move { from { transform: translate(-10%, -10%) scale(1); } to { transform: translate(20%, 20%) scale(1.2); } }
    </style>
</head>
<body>
    <div class="bg-blob" style="top: -100px; left: -100px;"></div>
    <div class="bg-blob" style="bottom: -100px; right: -100px; animation-delay: -5s;"></div>

    <div class="max-w-md w-full px-6 text-center">
        <div class="glass-panel p-10 rounded-[3rem] relative overflow-hidden">
            <div class="absolute top-0 right-0 p-6 opacity-10">
                <i class="fa-solid fa-wrench text-6xl -rotate-12"></i>
            </div>
            
            <div class="mb-8 floating">
                <div class="w-24 h-24 bg-white/40 rounded-3xl mx-auto flex items-center justify-center border border-white/50 shadow-xl">
                    <i class="fa-solid fa-screwdriver-wrench text-4xl text-themeDark/80"></i>
                </div>
            </div>

            <h1 class="font-dynapuff text-3xl font-black text-themeDark mb-4">We'll Be Back!</h1>
            <p class="text-sm font-medium text-themeDark/60 leading-relaxed mb-8">
                <?= htmlspecialchars($setting['store_name'] ?? 'JZ Store') ?> is currently undergoing scheduled maintenance to improve your experience.
            </p>

            <div class="space-y-4">
                <div class="p-4 bg-white/30 rounded-2xl border border-white/40">
                    <div class="text-[10px] font-black uppercase tracking-widest text-themeDark/40 mb-1">Expected Back In</div>
                    <div class="text-lg font-bold text-themeDark">About 2 Hours</div>
                </div>

                <div class="flex items-center justify-center gap-4 pt-4">
                    <a href="<?= htmlspecialchars($setting['whatsapp'] ?? '#') ?>" class="w-12 h-12 glass-panel rounded-2xl flex items-center justify-center text-xl hover:scale-110 transition shadow-lg border-white/60">
                        <i class="fa-brands fa-whatsapp text-green-600"></i>
                    </a>
                    <a href="<?= htmlspecialchars($setting['instagram'] ?? '#') ?>" class="w-12 h-12 glass-panel rounded-2xl flex items-center justify-center text-xl hover:scale-110 transition shadow-lg border-white/60">
                        <i class="fa-brands fa-instagram text-pink-600"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-10 opacity-60">
             <div class="font-dynapuff text-xl mb-1"><?= htmlspecialchars($setting['store_name'] ?? 'JZ Store') ?></div>
             <p class="text-[10px] font-bold uppercase tracking-widest">Premium Game Store</p>
        </div>
    </div>
</body>
</html>
