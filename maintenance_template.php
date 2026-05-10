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
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#08203E",endColorstr="#557C93",GradientType=1);
            background-attachment: fixed; 
            color: #ffffff; 
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.1); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
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
        .bg-blob { position: absolute; width: 500px; height: 500px; background: linear-gradient(135deg, rgba(8, 32, 62, 0.5) 0%, rgba(85, 124, 147, 0.5) 100%); filter: blur(80px); border-radius: 50%; animation: move 20s infinite alternate; z-index: -1; }
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
                <div class="w-24 h-24 bg-white/10 rounded-3xl mx-auto flex items-center justify-center border border-white/20 shadow-xl">
                    <i class="fa-solid fa-screwdriver-wrench text-4xl text-white/80"></i>
                </div>
            </div>

            <h1 class="font-dynapuff text-3xl font-black text-white mb-4">We'll Be Back!</h1>
            <p class="text-sm font-medium text-white/60 leading-relaxed mb-8">
                <?= htmlspecialchars($setting['store_name'] ?? 'JZ Store') ?> is currently undergoing scheduled maintenance to improve your experience.
            </p>

            <div class="space-y-4">
                <div class="p-4 bg-white/10 rounded-2xl border border-white/10">
                    <div class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Expected Back In</div>
                    <div class="text-lg font-bold text-white">About 2 Hours</div>
                </div>

                <div class="flex items-center justify-center gap-4 pt-4">
                    <a href="<?= htmlspecialchars($setting['whatsapp'] ?? '#') ?>" class="w-12 h-12 glass-panel rounded-2xl flex items-center justify-center text-xl hover:scale-110 transition shadow-lg">
                        <i class="fa-brands fa-whatsapp text-green-400"></i>
                    </a>
                    <a href="<?= htmlspecialchars($setting['instagram'] ?? '#') ?>" class="w-12 h-12 glass-panel rounded-2xl flex items-center justify-center text-xl hover:scale-110 transition shadow-lg">
                        <i class="fa-brands fa-instagram text-pink-400"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-10 opacity-60 text-white">
             <div class="font-dynapuff text-xl mb-1"><?= htmlspecialchars($setting['store_name'] ?? 'JZ Store') ?></div>
             <p class="text-[10px] font-bold uppercase tracking-widest">Premium Game Store</p>
        </div>
    </div>
</body>
</html>
