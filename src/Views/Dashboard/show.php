<?php include_once __DIR__ . '/../Commons/base_header.php'; ?>

<h2>Fichier: <?= htmlspecialchars($file['filename'] ?? '') ?></h2>

<table class="file-table">
    <tr>
        <th>Nom du fichier:</th>
        <th>Description:</th>
    </tr>
    <tr>
        <td><?= htmlspecialchars($file['filename'] ?? '') ?></td>
        <td><?= htmlspecialchars($file['description'] ?? '') ?></td>
    </tr>
</table>

<form action="/file/update-description" method="post">
    <div>
        <label for="new_description">Nouvelle description:</label>
        <textarea rows="10" cols="50" name="new_description" id="new_description"></textarea>
    </div>
    <input type="hidden" name="file_id" value="<?= htmlspecialchars($file['id'] ?? '', ENT_QUOTES) ?>">
    <input type="hidden" name="csrf" value="<?= $csrf ?? '' ?>" />
    <button type="submit">Mettre à jour</button>
</form>

<p>Taille: <?= htmlspecialchars($file['size'] ?? '') ?></p>
<p>Nombre de téléchargements: <?= htmlspecialchars($file['downloadCount'] ?? '') ?></p>
<p>Date de création: <?= htmlspecialchars($file['createdAt'] ?? '') ?></p>
<p>Is public: <?= $file['isPublic'] ?? false ? 'Yes' : 'No' ?> </p>

<?php if ($file['isPublic'] ?? false) : ?>
    <p>Public URL: <a href="<?= $base_url ?? '' ?>/dl/<?= $file['token'] ?? '' ?>" target="_blank"><?= $base_url ?? '' ?>/dl/<?= $file['token'] ?? '' ?></a></p>
<?php endif; ?>

<hr />

<form action="/public/<?= htmlspecialchars($file['id'] ?? '', ENT_QUOTES) ?>" method="post">
    <div>
        <label>
            <input type="checkbox" name="isPublic" <?= $file['isPublic'] ?? false ? 'checked' : '' ?> />
            Public
        </label>
    </div>
    <div>
        <label>
            <input type="checkbox" name="hasPassword" <?= $file['hasPassword'] ?? false ? 'checked' : '' ?> />
            Protégé par mot de passe
        </label>
    </div>
    <div>
        <label>
            <input type="password" name="password" placeholder="Mot de passe" />
        </label>
    </div>
    <input type="hidden" name="csrf" value="<?= $csrf ?? '' ?>" />
    <button type="submit">Valider</button>
</form>

<hr />

<a href="/dashboard">Retour au tableau de bord</a>

<?php if (!empty($comments)) : ?>
    <hr />
    <p>Comments:</p>
    <?php foreach ($comments as $comment) : ?>
        <p>From <?= htmlspecialchars($comment['firstname'] . ' ' . $comment['lastname']) ?></p>
        <div><?= htmlspecialchars($comment['content']) ?></div>

        <form action="/comment/delete/<?= htmlspecialchars($comment['id'] ?? '', ENT_QUOTES) ?>" method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?? '' ?>" />
            <button type="submit">Delete</button>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($messages)) : ?>
    <hr />
    <?php foreach ($messages as $message) : ?>
        <div><?= htmlspecialchars($message) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include_once __DIR__ . '/../Commons/base_footer.php'; ?>