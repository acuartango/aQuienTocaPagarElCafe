<?php
// index.php

// Cargar la base de datos de JSON
function loadData() {
    $filename = 'db.json';

    // Comprobar si el archivo existe
    if (!file_exists($filename)) {
        // Intentar crear el archivo con contenido inicial
        if (file_put_contents($filename, json_encode(['rounds' => []], JSON_PRETTY_PRINT)) === false) {
            error_log("No se pudo crear el archivo $filename. Verifica los permisos.");
            return ['rounds' => []]; // Devolver datos vacíos
        }
        chmod($filename, 0666); // Cambiar permisos para permitir escritura
    }

    // Cargar datos del archivo
    $json = file_get_contents($filename);
    return json_decode($json, true);
}
// Guardar los datos en el JSON
function saveData($data) {
    $filename = 'db.json';
    
    // Comprobar si el archivo es escribible
    if (!is_writable($filename)) {
        chmod($filename, 0666); // Cambiar permisos para permitir escritura
    }
    
    // Guardar los datos en el archivo
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
}

// Calcular deudas
function calculateDebts($participants, $rounds) {
    $totalDebts = [];
    
    // Inicializar deudas
    foreach ($participants as $participant) {
        $totalDebts[$participant] = 0;
    }
    
    // Sumar deudas de cada ronda
    foreach ($rounds as $round) {
        // Clonar y ordenar los arrays para la comparación
        $sortedParticipants = $participants;
        $sortedRoundParticipants = $round['participants'];
        sort($sortedParticipants);
        sort($sortedRoundParticipants);

        if ($sortedParticipants == $sortedRoundParticipants) {
            echo "<script>console.log('Datos desde PHP: ', " . json_encode($round) . ");</script>";
            $amountPerPerson = $round['amount'] / count($round['participants']);
            foreach ($round['participants'] as $participant) {
                if ($participant != $round['payer']){ // El que paga no suma deuda ese día
                    $totalDebts[$participant] += $amountPerPerson; // Deuda al resto
                }
            }
            echo "<script>console.log('Deuda parcial : ', " . json_encode($totalDebts) . ");</script>";
        }
    }
    echo "<script>console.log('Deudas actuales: ', " . json_encode($totalDebts) . ");</script>";
    
    // Encontrar la menor deuda
    $minDebt = min($totalDebts);

    // Restar la menor deuda de todas las deudas
    foreach ($totalDebts as $participant => $debt) {
        $totalDebts[$participant] -= $minDebt;
    }

    return $totalDebts;
}

// Procesar la solicitud
$data = loadData();
$debts = []; // Variable para almacenar deudas calculadas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['calculate'])) {
        // Obtener participantes
        $participants = explode(',', trim($_POST['participants']));
        // Convertir cada elemento a minúsculas
        $participants = array_map('strtolower', $participants);
        $debts = calculateDebts($participants, $data['rounds']);
    } elseif (isset($_POST['record'])) {
        // Registrar la ronda
        $amount = floatval($_POST['amount']);
        $participants = explode(',', trim($_POST['participants']));
        // Convertir cada elemento a minúsculas
        $participants = array_map('strtolower', $participants);
        $payer = strtolower(trim($_POST['payer'])); // Quien pagó
        $date = date('Y-m-d H:i:s'); // Fecha actual
        $data['rounds'][] = [
            'amount' => $amount,
            'participants' => $participants,
            'payer' => $payer,
            'date' => $date // Almacenar la fecha
        ];
        saveData($data);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Café Deudas</title>
</head>
<body>
    <h1>Cálculo de Deudas por Café</h1>

    <h2>Calcular Deudas</h2>
    <form method="post">
        <label for="participants">Asistentes (separados por comas):</label>
        <input type="text" name="participants" required>
        <button type="submit" name="calculate">Calcular</button>
    </form>

    <?php if (!empty($debts)): ?>
        <h3>Deudas:</h3>
        <ul>
            <?php foreach ($debts as $participant => $debt): ?>
                <li><?php echo htmlspecialchars(ucwords(strtolower($participant))) . ': ' . number_format($debt, 2) . '€'; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Registrar Ronda de Café</h2>
    <form method="post">
        <label for="amount">Importe Total (Solo se permiten números y coma):</label>
        <input type="number" name="amount" pattern="^[0-9]+([,][0-9]*)?$" step="0.01" required>
        <label for="participants">Asistentes (separados por comas):</label>
        <input type="text" name="participants" required>
        <label for="payer">Quien Pagó:</label>
        <input type="text" name="payer" required>
        <button type="submit" name="record">Registrar</button>
    </form>
</body>
</html>
