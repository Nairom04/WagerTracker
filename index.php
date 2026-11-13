<?php
declare(strict_types=1);

session_start();

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function validMonth(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m', $value);
    return $date !== false && $date->format('Y-m') === $value;
}

$month = $_GET['month'] ?? date('Y-m');
if (!is_string($month) || !validMonth($month)) {
    $month = date('Y-m');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$pdo = null;
$records = [];
$fundAdditions = [];
$stats = ['all_time_wins' => '0.00', 'cash_added' => '0.00', 'balance' => '0.00', 'wins' => '0.00', 'losses' => '0.00', 'net' => '0.00', 'winning_days' => 0, 'losing_days' => 0];

try {
    $pdo = require __DIR__ . '/config.php';
} catch (Throwable $exception) {
    error_log('WagerTrack database connection failed: ' . $exception->getMessage());
    $error = 'Could not connect to MySQL. Check that MySQL is running in XAMPP, then import database.sql.';
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session token expired. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_funds') {
            $transactionDate = $_POST['transaction_date'] ?? '';
            $amount = $_POST['amount'] ?? '';
            $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');

            if (!is_string($transactionDate) || !validDate($transactionDate)) {
                $error = 'Choose a valid date.';
            } elseif (!is_string($amount) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount) || (float)$amount <= 0) {
                $error = 'Enter an amount greater than zero with up to two decimal places.';
            } elseif (strlen($description) > 160) {
                $error = 'The description must be 160 characters or fewer.';
            } else {
                try {
                    $statement = $pdo->prepare(
                        'INSERT INTO cash_additions (transaction_date, amount, description)
                         VALUES (:transaction_date, :amount, :description)'
                    );
                    $statement->execute([
                        'transaction_date' => $transactionDate,
                        'amount' => $amount,
                        'description' => $description,
                    ]);
                    $_SESSION['notice'] = 'Money added to your balance.';
                    header('Location: ?month=' . urlencode(substr($transactionDate, 0, 7)));
                    exit;
                } catch (Throwable $exception) {
                    error_log('WagerTrack money addition failed: ' . $exception->getMessage());
                    $error = 'Money could not be added. Check the database setup and try again.';
                }
            }
        } else {
            $recordDate = $_POST['record_date'] ?? '';
            if (!is_string($recordDate) || !validDate($recordDate)) {
                $error = 'Choose a valid date.';
            } elseif ($action === 'save') {
                $result = $_POST['result'] ?? '';
                $amount = $_POST['amount'] ?? '';
                $notes = trim(is_string($_POST['notes'] ?? null) ? $_POST['notes'] : '');

                if (!in_array($result, ['win', 'loss'], true)) {
                    $error = 'Choose win or loss.';
                } elseif (!is_string($amount) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount) || (float)$amount <= 0) {
                    $error = 'Enter an amount greater than zero with up to two decimal places.';
                } elseif (strlen($notes) > 500) {
                    $error = 'Notes must be 500 characters or fewer.';
                } else {
                    try {
                        $statement = $pdo->prepare(
                            'INSERT INTO daily_records (record_date, result, amount, notes)
                             VALUES (:record_date, :result, :amount, :notes)
                             ON DUPLICATE KEY UPDATE result = VALUES(result), amount = VALUES(amount), notes = VALUES(notes)'
                        );
                        $statement->execute([
                            'record_date' => $recordDate,
                            'result' => $result,
                            'amount' => $amount,
                            'notes' => $notes,
                        ]);
                        $_SESSION['notice'] = 'Record saved.';
                        header('Location: ?month=' . urlencode(substr($recordDate, 0, 7)));
                        exit;
                    } catch (Throwable $exception) {
                        error_log('WagerTrack record save failed: ' . $exception->getMessage());
                        $error = 'The record could not be saved. Check the database setup and try again.';
                    }
                }
            } elseif ($action === 'delete') {
                try {
                    $statement = $pdo->prepare('DELETE FROM daily_records WHERE record_date = :record_date');
                    $statement->execute(['record_date' => $recordDate]);
                    $_SESSION['notice'] = 'Record deleted.';
                    header('Location: ?month=' . urlencode(substr($recordDate, 0, 7)));
                    exit;
                } catch (Throwable $exception) {
                    error_log('WagerTrack record delete failed: ' . $exception->getMessage());
                    $error = 'The record could not be deleted. Try again.';
                }
            } else {
                $error = 'That action is not supported.';
            }
        }
    }
}

$monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
$monthEnd = $monthStart->modify('last day of this month');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare(
            'SELECT record_date, result, amount, notes
             FROM daily_records
             WHERE record_date BETWEEN :start_date AND :end_date'
        );
        $statement->execute([
            'start_date' => $monthStart->format('Y-m-d'),
            'end_date' => $monthEnd->format('Y-m-d'),
        ]);
        foreach ($statement->fetchAll() as $record) {
            $records[$record['record_date']] = $record;
        }

        $statement = $pdo->prepare(
            "SELECT
                (SELECT COALESCE(SUM(amount), 0) FROM daily_records WHERE result = 'win') AS all_time_wins,
                COALESCE(SUM(CASE WHEN result = 'win' THEN amount ELSE 0 END), 0) AS wins,
                COALESCE(SUM(CASE WHEN result = 'loss' THEN amount ELSE 0 END), 0) AS losses,
                COALESCE(SUM(CASE WHEN result = 'win' THEN amount ELSE -amount END), 0) AS net,
                COALESCE(SUM(result = 'win'), 0) AS winning_days,
                COALESCE(SUM(result = 'loss'), 0) AS losing_days
             FROM daily_records
             WHERE record_date BETWEEN :start_date AND :end_date"
        );
        $statement->execute([
            'start_date' => $monthStart->format('Y-m-d'),
            'end_date' => $monthEnd->format('Y-m-d'),
        ]);
        $stats = $statement->fetch() ?: $stats;

        $statement = $pdo->query(
            "SELECT
                COALESCE((SELECT SUM(amount) FROM cash_additions), 0) AS cash_added,
                COALESCE((SELECT SUM(amount) FROM cash_additions), 0)
                    + COALESCE((SELECT SUM(CASE WHEN result = 'win' THEN amount ELSE -amount END) FROM daily_records), 0) AS balance"
        );
        $balanceTotals = $statement->fetch();
        $stats['cash_added'] = $balanceTotals['cash_added'];
        $stats['balance'] = $balanceTotals['balance'];

        $statement = $pdo->query(
            'SELECT transaction_date, amount, description
             FROM cash_additions
             ORDER BY transaction_date DESC, id DESC
             LIMIT 5'
        );
        $fundAdditions = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('WagerTrack data load failed: ' . $exception->getMessage());
        $error = 'Records or balance could not be loaded. Check the database setup and refresh the page.';
    }
}

$monthLabel = $monthStart->format('F Y');
$offset = (int)$monthStart->format('N') - 1;
$daysInMonth = (int)$monthEnd->format('j');
$cellCount = (int)(ceil(($offset + $daysInMonth) / 7) * 7);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WagerTrack — Personal results</title>
    <link rel="stylesheet" href="style.css">
    <script src="app.js" defer></script>
</head>
<body>
<main class="app-shell">
    <header class="topbar">
        <a class="brand" href="?month=<?= escape($month) ?>" aria-label="WagerTrack home">
            <span class="brand-mark" aria-hidden="true">W</span>
            <span>Wager<span class="brand-light">Track</span></span>
        </a>
        <span class="topbar-note">Your personal results, at a glance.</span>
    </header>

    <section class="intro">
        <div>
            <p class="eyebrow">PERSONAL TRACKER</p>
            <h1>Make every day <span>count.</span></h1>
            <p class="intro-copy">A clear view of your wins, losses, and the details behind them.</p>
        </div>
        <div class="intro-actions">
            <button class="button button-quiet" type="button" id="add-funds">
                <span aria-hidden="true">+</span> Add money
            </button>
            <button class="button button-primary" type="button" id="add-record">
                <span aria-hidden="true">+</span> Add a record
            </button>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="message message-error" role="alert"><?= escape($error) ?></div>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
        <div class="message message-success" role="status"><?= escape((string)$notice) ?></div>
    <?php endif; ?>

    <section class="balance-card" aria-label="Current balance">
        <div>
            <span class="balance-label">Current balance</span>
            <?php $balance = (float)$stats['balance']; ?>
            <strong class="balance-value <?= $balance >= 0 ? 'positive' : 'negative' ?>">
                <?= number_format($balance, 2) ?>
            </strong>
            <span class="balance-caption">Money added, plus all recorded wins and losses</span>
        </div>
        <div class="balance-added">
            <span class="stat-label">Money added</span>
            <strong>+<?= number_format((float)$stats['cash_added'], 2) ?></strong>
        </div>
    </section>

    <section class="stats-grid" aria-label="Winnings and monthly summary">
        <article class="stat-card">
            <span class="stat-label">All-time winnings</span>
            <strong class="stat-value positive">+<?= number_format((float)$stats['all_time_wins'], 2) ?></strong>
            <span class="stat-caption">Across all recorded months</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Total winnings</span>
            <strong class="stat-value positive">+<?= number_format((float)$stats['wins'], 2) ?></strong>
            <span class="stat-caption"><?= (int)$stats['winning_days'] ?> winning <?= (int)$stats['winning_days'] === 1 ? 'day' : 'days' ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Total losses</span>
            <strong class="stat-value negative">−<?= number_format((float)$stats['losses'], 2) ?></strong>
            <span class="stat-caption"><?= (int)$stats['losing_days'] ?> losing <?= (int)$stats['losing_days'] === 1 ? 'day' : 'days' ?></span>
        </article>
        <article class="stat-card stat-card-net">
            <span class="stat-label">Net result</span>
            <?php $net = (float)$stats['net']; ?>
            <strong class="stat-value <?= $net >= 0 ? 'positive' : 'negative' ?>">
                <?= $net > 0 ? '+' : ($net < 0 ? '−' : '') ?><?= number_format(abs($net), 2) ?>
            </strong>
            <span class="stat-caption">For <?= escape($monthLabel) ?></span>
        </article>
    </section>

    <?php if ($fundAdditions !== []): ?>
        <section class="funds-history" aria-label="Recent money added">
            <h2>Recent money added</h2>
            <ul>
                <?php foreach ($fundAdditions as $addition): ?>
                    <li>
                        <span class="fund-description">
                            <strong><?= $addition['description'] !== '' ? escape($addition['description']) : 'Money added' ?></strong>
                            <span><?= escape((new DateTimeImmutable($addition['transaction_date']))->format('M j, Y')) ?></span>
                        </span>
                        <strong class="positive">+<?= number_format((float)$addition['amount'], 2) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="calendar-panel">
        <div class="calendar-heading">
            <div>
                <p class="eyebrow">YOUR ACTIVITY</p>
                <h2><?= escape($monthLabel) ?></h2>
            </div>
            <nav class="month-nav" aria-label="Calendar navigation">
                <a class="icon-button" href="?month=<?= escape($previousMonth) ?>" aria-label="Previous month">‹</a>
                <a class="today-link" href="?month=<?= date('Y-m') ?>">Today</a>
                <a class="icon-button" href="?month=<?= escape($nextMonth) ?>" aria-label="Next month">›</a>
            </nav>
        </div>

        <div class="calendar-grid" role="grid" aria-label="<?= escape($monthLabel) ?>">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
                <div class="weekday" role="columnheader"><?= $weekday ?></div>
            <?php endforeach; ?>
            <?php for ($cell = 0; $cell < $cellCount; $cell++):
                $day = $cell - $offset + 1;
                $inMonth = $day >= 1 && $day <= $daysInMonth;
                $dateKey = $inMonth ? $monthStart->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT) : '';
                $record = $inMonth ? ($records[$dateKey] ?? null) : null;
                $isToday = $dateKey === date('Y-m-d');
                ?>
                <button
                    class="day-cell<?= $inMonth ? '' : ' day-outside' ?><?= $record ? ' day-' . escape($record['result']) : '' ?><?= $isToday ? ' day-today' : '' ?>"
                    type="button"
                    role="gridcell"
                    <?= $inMonth ? '' : 'disabled' ?>
                    <?php if ($inMonth): ?>
                        data-date="<?= escape($dateKey) ?>"
                        data-result="<?= $record ? escape($record['result']) : '' ?>"
                        data-amount="<?= $record ? escape((string)$record['amount']) : '' ?>"
                        data-notes="<?= $record ? escape($record['notes']) : '' ?>"
                        aria-label="<?= escape($monthStart->format('F')) ?> <?= $day ?><?= $record ? ', ' . escape($record['result']) . ' ' . escape((string)$record['amount']) : ', no record' ?>"
                    <?php endif; ?>
                >
                    <?php if ($inMonth): ?>
                        <span class="day-number"><?= $day ?></span>
                        <?php if ($record): ?>
                            <span class="day-amount"><?= $record['result'] === 'win' ? '+' : '−' ?><?= number_format((float)$record['amount'], 2) ?></span>
                            <?php if ($record['notes'] !== ''): ?><span class="note-indicator">Note added</span><?php endif; ?>
                        <?php else: ?>
                            <span class="day-empty">＋</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </button>
            <?php endfor; ?>
        </div>
        <div class="calendar-legend">
            <span><i class="legend-dot legend-win"></i> Winning day</span>
            <span><i class="legend-dot legend-loss"></i> Losing day</span>
            <span class="legend-hint">Select any date to add or edit a record</span>
        </div>
    </section>
    <footer>WagerTrack <span>·</span> Keep your records clear and personal.</footer>
</main>

<dialog class="record-dialog" id="record-dialog">
    <form method="post" id="record-form">
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="save">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">DAILY RESULT</p>
                <h2 id="dialog-title">Add a record</h2>
            </div>
            <button type="button" class="icon-button close-dialog" aria-label="Close">×</button>
        </div>
        <label class="field-label" for="record-date">Date</label>
        <input class="field-input" type="date" name="record_date" id="record-date" required>
        <fieldset class="result-picker">
            <legend class="field-label">Result</legend>
            <label class="result-option result-win">
                <input type="radio" name="result" value="win" required>
                <span>Win</span>
            </label>
            <label class="result-option result-loss">
                <input type="radio" name="result" value="loss" required>
                <span>Loss</span>
            </label>
        </fieldset>
        <label class="field-label" for="record-amount">Amount</label>
        <div class="amount-input">
            <span>＋ / −</span>
            <input class="field-input" type="number" name="amount" id="record-amount" min="0.01" max="9999999999.99" step="0.01" placeholder="0.00" required>
        </div>
        <label class="field-label" for="record-notes">Notes <span class="optional">(optional)</span></label>
        <textarea class="field-input notes-input" name="notes" id="record-notes" maxlength="500" rows="3" placeholder="Add a detail to remember..."></textarea>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet close-dialog">Cancel</button>
            <button type="submit" class="button button-primary">Save record</button>
        </div>
    </form>
    <form method="post" id="delete-form" class="delete-form">
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="record_date" id="delete-date">
        <button type="submit" class="delete-button">Delete this record</button>
    </form>
</dialog>

<dialog class="record-dialog" id="funds-dialog">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="add_funds">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">BALANCE ADJUSTMENT</p>
                <h2>Add money</h2>
            </div>
            <button type="button" class="icon-button close-dialog" aria-label="Close">×</button>
        </div>
        <p class="funds-help">Add your starting balance or any other money you put into your funds. Your wager results are added to or subtracted from this amount automatically.</p>
        <label class="field-label" for="transaction-date">Date</label>
        <input class="field-input" type="date" name="transaction_date" id="transaction-date" value="<?= date('Y-m-d') ?>" required>
        <label class="field-label" for="fund-amount">Amount</label>
        <div class="amount-input">
            <span>＋</span>
            <input class="field-input" type="number" name="amount" id="fund-amount" min="0.01" max="9999999999.99" step="0.01" placeholder="0.00" required>
        </div>
        <label class="field-label" for="fund-description">Description <span class="optional">(optional)</span></label>
        <input class="field-input" type="text" name="description" id="fund-description" maxlength="160" placeholder="Starting balance, top-up...">
        <div class="dialog-actions">
            <button type="button" class="button button-quiet close-dialog">Cancel</button>
            <button type="submit" class="button button-primary">Add to balance</button>
        </div>
    </form>
</dialog>
</body>
</html>
