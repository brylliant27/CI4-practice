<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Climate data</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        form { display: flex; gap: 1rem; align-items: end; flex-wrap: wrap; }
        label { display: flex; flex-direction: column; font-size: .9rem; gap: .25rem; }
        .error { background: #fde8e8; color: #9b1c1c; padding: .75rem 1rem; border-radius: 6px; margin-top: 1rem; }
        pre { background: #f4f4f5; padding: 1rem; border-radius: 6px; overflow-x: auto; max-height: 500px; }
    </style>
</head>
<body>
    <h1>Climate data</h1>

    <form method="get" action="<?= site_url('climate') ?>">
        <label>
            Start date
            <input type="date" name="start_date" value="<?= esc($start ?? '') ?>"
                   min="1950-01-01" max="2050-12-31" required>
        </label>
        <label>
            End date
            <input type="date" name="end_date" value="<?= esc($end ?? '') ?>"
                   min="1950-01-01" max="2050-12-31" required>
        </label>
        <button type="submit">Fetch</button>
    </form>

    <?php if ($error): ?>
        <div class="error"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php if ($json): ?>
        <pre><?= esc($json) ?></pre>
    <?php endif; ?>
</body>
</html>