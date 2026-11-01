<?php
/** @var FlightResponse[] $flights */
?>

<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

    <h1>Lista de vuelos</h1>

<?php if (!empty($message)): ?>
    <div class="alert-error">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (empty($flights)): ?>
    <p>No hay vuelos registrados todavía.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Vuelo</th>
            <th>Salida</th>
            <th>Ruta</th>
            <th>Cliente</th>
            <th>Puesto</th>
            <th>Valor</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($flights as $flight): ?>
            <tr>
                <td><?= htmlspecialchars($flight->getFlightNumber(), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(substr($flight->getDepartureDate(), 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?= htmlspecialchars($flight->getDepartureAirport(), ENT_QUOTES, 'UTF-8') ?>
                    &rarr;
                    <?= htmlspecialchars($flight->getArrivalAirport(), ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td><?= htmlspecialchars($flight->getCustomer(), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($flight->getSeat(), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(number_format((float) $flight->getPrice(), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($flight->getStatus(), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="col-actions">
                    <a class="btn btn-sm" href="?route=flights.show&amp;id=<?= urlencode($flight->getId()) ?>">Ver</a>
                    <a class="btn btn-warning btn-sm"
                       href="?route=flights.edit&amp;id=<?= urlencode($flight->getId()) ?>">Editar</a>
                    <form method="POST" action="?route=flights.delete" style="display:inline;"
                          onsubmit="return confirmDelete(this);"
                          data-label="el vuelo"
                          data-name="<?= htmlspecialchars($flight->getFlightNumber() . ' (puesto ' . $flight->getSeat() . ')', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($flight->getId(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>