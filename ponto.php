<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Registro de Ponto</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #matricula-section {
            max-width: 350px;
            margin: 0 auto 1.5rem auto;
            background: #fff;
            border-radius: 7px;
            padding: 1.5rem 1rem;
            box-shadow: 0 3px 14px #0001;
        }
        #matricula {
            font-size: 1.3rem;
            font-weight: bold;
            text-align: center;
            letter-spacing: 2px;
        }
        #btn-matricula {
            width: 100%;
            font-size: 1.1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Registro de Ponto</h2>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </div>
        <div class="row justify-content-center mb-4">
            <div class="col-12 col-md-8 col-lg-6">
                <div id="reader" style="width: 100%; min-width:340px; min-height:320px; margin: 0 auto;"></div>
            </div>
        </div>
        <div id="matricula-section">
            <label for="matricula" class="form-label">Ou digite sua matrícula (6 dígitos):</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control mb-2" id="matricula" placeholder="Somente números" autocomplete="off">
            <button id="btn-matricula" class="btn btn-primary">Registrar Ponto</button>
        </div>
        <div id="resultado" class="text-center mb-4"></div>
    </div>
    <script>
        let html5QrcodeScanner;

        function exibeResultado(msg, tempo, recarregarQR = true) {
            document.getElementById("resultado").innerHTML = msg;
            if (recarregarQR && html5QrcodeScanner) {
                setTimeout(() => {
                    document.getElementById("resultado").innerHTML = '';
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                }, tempo);
            }
        }

        function onScanSuccess(decodedText) {
            if (html5QrcodeScanner) html5QrcodeScanner.clear();
            document.getElementById("resultado").innerText = "QR Lido! Processando...";

            fetch("registrar_ponto.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ dados: decodedText })
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "funcionario_nao_encontrado") {
                    exibeResultado('<span class="text-danger fw-bold">Funcionário não encontrado</span>', 2000);
                } else if (data.trim() === "funcionario_inativo") {
                    exibeResultado('<span class="text-danger fw-bold">Funcionário INATIVO! Registro de ponto não permitido.</span>', 4000);
                } else {
                    document.getElementById("resultado").innerHTML = data;
                    setTimeout(() => {
                        window.location.href = "ponto.php";
                    }, 5000);
                }
            });
        }

        function onScanFailure(error) {
            // Exibe erro de câmera, mas mantém campo de matrícula sempre disponível
            if (error && typeof error === "string" && error.includes("Permission")) {
                document.getElementById("resultado").innerHTML = '<span class="text-danger fw-bold">Não foi possível acessar a câmera. Use a matrícula abaixo.</span>';
            }
        }

        function registrarPorMatricula() {
            const input = document.getElementById("matricula");
            const matricula = input.value.trim();
            if (!/^\d{6}$/.test(matricula)) {
                document.getElementById("resultado").innerHTML = '<span class="text-danger fw-bold">Digite uma matrícula válida (6 números)</span>';
                input.focus();
                return;
            }
            document.getElementById("resultado").innerText = "Processando matrícula...";
            fetch("registrar_ponto.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ matricula: matricula })
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "funcionario_nao_encontrado") {
                    exibeResultado('<span class="text-danger fw-bold">Funcionário não encontrado</span>', 2000, false);
                } else if (data.trim() === "funcionario_inativo") {
                    exibeResultado('<span class="text-danger fw-bold">Funcionário INATIVO! Registro de ponto não permitido.</span>', 4000, false);
                } else {
                    document.getElementById("resultado").innerHTML = data;
                    setTimeout(() => {
                        window.location.href = "ponto.php";
                    }, 5000);
                }
            });
            input.value = '';
        }

        window.onload = function() {
            if (window.Html5QrcodeScanner) {
                html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 350 });
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            }
            document.getElementById("btn-matricula").onclick = registrarPorMatricula;
            document.getElementById("matricula").onkeyup = function(e) {
                if (e.key === "Enter") registrarPorMatricula();
            };
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
