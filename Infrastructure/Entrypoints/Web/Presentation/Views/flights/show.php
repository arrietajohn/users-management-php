<?php
/** @var FlightResponse $flight */
?>

<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

    <h1>Detalle de vuelo</h1>

<?php if (!empty($message)): ?>
    <div class="alert-error">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

    <table class="detail-table">
        <tr>
            <th>ID</th>
            <td><?= htmlspecialchars($flight->getId(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Número de vuelo</th>
            <td><?= htmlspecialchars($flight->getFlightNumber(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Estado</th>
            <td><?= htmlspecialchars($flight->getStatus(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Aerolínea</th>
            <td><?= htmlspecialchars($flight->getAirline(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Agencia de viajes</th>
            <td><?= htmlspecialchars($flight->getTravelAgency(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Cliente</th>
            <td><?= htmlspecialchars($flight->getCustomer(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Puesto</th>
            <td><?= htmlspecialchars($flight->getSeat(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Avión</th>
            <td><?= htmlspecialchars($flight->getAircraft(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Piloto</th>
            <td><?= htmlspecialchars($flight->getPilot(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Aeropuerto de salida</th>
            <td><?= htmlspecialchars($flight->getDepartureAirport(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Aeropuerto de llegada</th>
            <td><?= htmlspecialchars($flight->getArrivalAirport(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Fecha de compra</th>
            <td><?= htmlspecialchars($flight->getPurchaseDate(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Fecha de salida</th>
            <td><?= htmlspecialchars($flight->getDepartureDate(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Fecha de llegada</th>
            <td><?= htmlspecialchars($flight->getArrivalDate(), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th>Valor</th>
            <td><?= htmlspecialchars(number_format((float) $flight->getPrice(), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>

    <p style="margin-top: 16px;">
        <a class="btn btn-warning" href="?route=flights.edit&amp;id=<?= urlencode($flight->getId()) ?>">Editar</a>
        &nbsp;
        <a class="btn" href="?route=flights.index">Volver al listado</a>
    </p>

<?php require __DIR__ . '/../layouts/footer.php'; ?>