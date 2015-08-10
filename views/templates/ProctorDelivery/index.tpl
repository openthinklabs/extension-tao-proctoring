<div class="delivery-manager">
    <h1><?= get_data('delivery')->getLabel() ?></h1>

    <aside class="stats-panel">

    </aside>

    <section class="delivery">
        <div class="list" data-id="<?= get_data('delivery')->getUri() ?>" data-set="<?= count(get_data('testTakers')) ? _dh(json_encode(get_data('testTakers'))) : ''; ?>">
            <h2>
                <span class="loading"><?= __("Loading") ?>...</span>
            </h2>
        </div>
    </section>
</div>

