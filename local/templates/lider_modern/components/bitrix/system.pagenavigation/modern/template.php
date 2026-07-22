<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(false);

$NavPageNomer = (int)($arResult['NavPageNomer'] ?? 1);
$NavPageCount = (int)($arResult['NavPageCount'] ?? 1);
$NavNum = (int)($arResult['NavNum'] ?? 1);
$sUrlPath = $arResult['sUrlPath'] ?? '';
$NavQueryString = $arResult['NavQueryString'] ?? '';
$bSavePage = ($arResult['bSavePage'] ?? false) === true;

if ($NavPageCount <= 1) return;

// Строим URL для страницы
function makePageUrl($sUrlPath, $NavQueryString, $page, $NavNum, $bSavePage)
{
    $params = [];
    if ($NavQueryString) {
        parse_str($NavQueryString, $params);
    }
    $params['PAGEN_' . $NavNum] = $page;
    if ($bSavePage) {
        $params['PAGEN_' . $NavNum] = $page;
    }
    $query = http_build_query($params, '', '&');
    return $sUrlPath . ($query ? '?' . $query : '');
}

// Определяем диапазон показываемых страниц
$nStartPage = max(1, $NavPageNomer - 2);
$nEndPage = min($NavPageCount, $NavPageNomer + 2);

// Показываем первую страницу
if ($nStartPage > 2) {
    $nStartPage = max(1, $NavPageNomer - 2);
    // показать "1 ..." если диапазон начинается позже
}
?>
<div class="pagination">
    <?php if ($NavPageNomer > 1): ?>
        <a href="<?= makePageUrl($sUrlPath, $NavQueryString, $NavPageNomer - 1, $NavNum, $bSavePage) ?>">←</a>
    <?php endif; ?>

    <?php if ($nStartPage > 1): ?>
        <a href="<?= makePageUrl($sUrlPath, $NavQueryString, 1, $NavNum, $bSavePage) ?>">1</a>
        <?php if ($nStartPage > 2): ?>
            <span>…</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $nStartPage; $i <= $nEndPage; $i++): ?>
        <?php if ($i == $NavPageNomer): ?>
            <span class="active"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= makePageUrl($sUrlPath, $NavQueryString, $i, $NavNum, $bSavePage) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($nEndPage < $NavPageCount): ?>
        <?php if ($nEndPage < $NavPageCount - 1): ?>
            <span>…</span>
        <?php endif; ?>
        <a href="<?= makePageUrl($sUrlPath, $NavQueryString, $NavPageCount, $NavNum, $bSavePage) ?>"><?= $NavPageCount ?></a>
    <?php endif; ?>

    <?php if ($NavPageNomer < $NavPageCount): ?>
        <a href="<?= makePageUrl($sUrlPath, $NavQueryString, $NavPageNomer + 1, $NavNum, $bSavePage) ?>">→</a>
    <?php endif; ?>
</div>
