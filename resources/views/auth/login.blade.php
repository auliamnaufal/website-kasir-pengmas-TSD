<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir UMKM TSD</title>
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"> -->
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            background-color: #fff;
            border-radius: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.35);
            position: relative;
            overflow: hidden;
            width: 768px;
            max-width: 100%;
            min-height: 480px;
            transition: background-color 0.3s ease, backdrop-filter 0.3s ease;
        }

        .container p {
            font-size: 14px;
            line-height: 20px;
            letter-spacing: 0.3px;
            margin: 20px 0;
            color: #333;
        }

        .container span {
            font-size: 12px;
            color: #666;
        }

        .container a {
            color: #512da8;
            font-size: 13px;
            text-decoration: none;
            margin: 15px 0 10px;
        }

        .container button {
            background-color: #512da8;
            color: #fff;
            font-size: 12px;
            padding: 10px 45px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 10px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .container button.hidden {
            background-color: transparent;
            border-color: #fff;
            color: #fff;
        }

        .container button:hover:not(.hidden) {
            background-color: #381880;
        }

        .container form {
            background-color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 40px;
            height: 100%;
            transition: padding 0.3s ease;
        }

        .container input {
            background-color: #eee;
            border: none;
            margin: 8px 0;
            padding: 10px 15px;
            font-size: 13px;
            border-radius: 8px;
            width: 100%;
            outline: none;
            transition: background-color 0.3s ease;
            color: #333;
        }

        .container input:focus {
            background-color: #ddd;
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            transition: all 0.6s ease-in-out;
        }

        .sign-in {
            left: 0;
            width: 50%;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
        }

        .container.active .sign-in {
            transform: translateX(100%);
        }

        .sign-up {
            left: 0;
            width: 50%;
            opacity: 0;
            z-index: 1;
        }

        .container.active .sign-up {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            animation: move 0.6s;
        }

        h1 {
            margin-bottom: 20px;
            color: #333;
            font-size: 28px;
        }

        .logo-image {
            max-width: 200px;
            height: auto;
            display: block;
            margin: auto;
        }

        @keyframes move {
            0%,
            49.99% {
                opacity: 0;
                z-index: 1;
            }

            50%,
            100% {
                opacity: 1;
                z-index: 5;
            }
        }

        .social-icons {
            margin: 20px 0;
        }

        .social-icons a {
            border: 1px solid #ccc;
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin: 0 5px;
            width: 40px;
            height: 40px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            color: #512da8;
            font-size: 18px;
        }

        .social-icons a:hover {
            background-color: #512da8;
            color: #fff;
            border-color: #512da8;
        }

        .toggle-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: all 0.6s ease-in-out;
            border-radius: 150px 0 0 100px;
            z-index: 1000;
        }

        .container.active .toggle-container {
            transform: translateX(-100%);
            border-radius: 0 150px 100px 0;
        }

        .toggle {
            background-color: #512da8;
            height: 100%;
            background: linear-gradient(to right, #5c6bc0, #512da8);
            color: #fff;
            position: relative;
            left: -100%;
            width: 200%;
            transform: translateX(0);
            transition: all 0.6s ease-in-out;
        }

        .container.active .toggle {
            transform: translateX(50%);
        }

        .toggle-panel {
            position: absolute;
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 30px;
            text-align: center;
            top: 0;
            transition: all 0.6s ease-in-out;
        }

        .toggle-left {
            transform: translateX(-200%);
        }

        .container.active .toggle-left {
            transform: translateX(0);
        }

        .toggle-right {
            right: 0;
            transform: translateX(0);
        }

        .container.active .toggle-right {
            transform: translateX(200%);
        }

        .error-message {
            color: red;
            margin: 15px 0;
            width: 100%;
            text-align: center;
        }

        /* Media Queries for responsiveness below 700px */
        @media (max-width: 700px) {
            body {
                padding: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            }

            .container {
                width: 100%;
                max-width: 400px;
                min-height: auto;
                padding: 20px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background-color: rgba(255, 255, 255, 0.95); /* Slight transparency */
                backdrop-filter: none;
                border-radius: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                transition: background-color 0.3s ease;
            }

            .toggle-container {
                display: none; /* Hide toggle container on mobile */
            }

            .form-container {
                position: relative;
                width: 100%;
                height: auto;
                transition: none;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                background-color: transparent;
            }

            .sign-in,
            .sign-up {
                width: 100%;
                left: 0;
                transform: translateX(0); /* Reset transform */
                opacity: 1; /* Ensure visibility */
                z-index: 2;
            }

            .container.active .sign-in,
            .container.active .sign-up {
                transform: translateX(0);
            }

            .container form {
                padding: 20px;
                width: 100%;
                background-color: #f9f9f9;
                border-radius: 10px;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            }

            /* Adjust font sizes for mobile */
            .container h1 {
                font-size: 24px;
                color: #333;
            }

            .container p {
                font-size: 14px;
                text-align: center;
                margin: 10px 0;
                color: #555;
            }

            .container a {
                font-size: 13px;
                margin: 10px 0;
                color: #512da8;
            }

            .container input {
                background-color: #e2e2e2;
                font-size: 14px;
                padding: 12px 20px;
                margin: 10px 0;
                color: #333;
            }

            .container button {
                font-size: 14px;
                padding: 12px 20px;
                width: 100%;
                background-color: #512da8;
            }

            .social-icons {
                margin: 20px 0;
            }

            .social-icons a {
                width: 35px;
                height: 35px;
            }

            .social-icons a i {
                font-size: 18px;
            }

            .logo-image {
                max-width: 150px;
            }

            .error-message {
                font-size: 14px;
                color: red;
            }
        }
    </style>
</head>

<body>

    <div class="container" id="container">
        <!-- Panel for image in "Welcome Back" -->
        <div class="form-container sign-in">
            <img src="/assets/images/loginbig.png" alt="Logo" class="logo-image" style="height: 300px; width: auto;">
        </div>

        <!-- Toggle Panels -->
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1 style="color: #fff;">Selamat Datang</h1>
                    <p style="color: #fff;">"Optimalkan usaha Anda dengan Kasir UMKM"</p>
                    <button class="hidden" id="login">Kembali</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1 style="color: #fff;">Hello, Sobat UMKM!</h1>
                    <p style="color: #fff;">Selamat datang di Kasir UMKM! Selamat bekerja dan jangan lupa bahagia!</p>
                    <button class="hidden" id="Supported">Mulai</button>
                </div>
            </div>
        </div>

        <!-- Login Form in "Hello, Sobat UMKM" -->
        <div class="form-container sign-up">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <h1>Log in</h1>

                @if ($errors->any())
                <div class="error-message">
                    {{ $errors->first() }}
                </div>
                @endif

                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <button type="submit">Log in</button>
            </form>
        </div>
    </div>

    <script>
        const container = document.getElementById('container');
        const supportedBtn = document.getElementById('Supported');
        const loginBtn = document.getElementById('login');

        if (supportedBtn && loginBtn) {
            supportedBtn.addEventListener('click', () => {
                container.classList.add("active");
            });

            loginBtn.addEventListener('click', () => {
                container.classList.remove("active");
            });
        }
    </script>
</body>

</html>
