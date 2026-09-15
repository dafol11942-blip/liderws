<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Каталог оригинальных автозапчастей и подбор по VIN/Frame-номеру онлайн — автотехцентр ЛИДЕР в Елабуге.");
$APPLICATION->SetPageProperty("title", "Каталог запчастей и подбор по VIN — автотехцентр ЛИДЕР в Елабуге");
$APPLICATION->SetTitle("Каталог и подбор запчастей по VIN");

// Если пришли из общего поиска сайта (search/index.php распознал VIN/номер кузова
// в строке поиска и перенаправил сюда) — виджет каталога стороннего домена, вставить
// в него значение напрямую нельзя, поэтому показываем VIN отдельно, чтобы пользователь
// мог быстро скопировать его в поле поиска внутри виджета.
$vinFromSearch = trim($_GET['vin'] ?? '');
if ($vinFromSearch !== '' && !preg_match('/^[A-Z0-9-]{5,17}$/', $vinFromSearch)) {
    $vinFromSearch = '';
}
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li>Каталог и подбор по VIN</li>
    </ul>
</div>

<div class="container">
    <div class="section-header">
        <h1 class="section-title"><svg class="icon"><use href="#icon-car"></use></svg> Каталог и подбор запчастей по VIN</h1>
    </div>

    <style>
        .vin-catalog-wrap {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 8px;
            overflow: hidden;
        }
        .vin-catalog-wrap iframe {
            display: block;
            width: 100%;
            border: 0;
        }
        .vin-hint {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .vin-hint code {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .5px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 4px 10px;
        }
        .vin-hint button {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            font-size: 13px;
            cursor: pointer;
        }
        .vin-hint button:hover { border-color: var(--blue); color: var(--blue); }
    </style>

    <?php if ($vinFromSearch !== ''): ?>
    <div class="vin-hint">
        <span>Мы распознали VIN/номер кузова из поиска: <code id="vinHintValue"><?= htmlspecialchars($vinFromSearch) ?></code></span>
        <button type="button" id="vinHintCopy" onclick="
            navigator.clipboard.writeText(document.getElementById('vinHintValue').textContent).then(function(){
                var b = document.getElementById('vinHintCopy'); var t = b.textContent; b.textContent = 'Скопировано!';
                setTimeout(function(){ b.textContent = t; }, 1500);
            });
        ">Скопировать</button>
        <span>— вставьте его в поле поиска каталога ниже</span>
    </div>
    <?php endif; ?>

    <div class="vin-catalog-wrap">
        <iframe id="acat-frame" src="https://liderws.acat.online" width="100%" height="600" scrolling="auto" frameborder="0" onload="scrollToAcatFrameBegin()" allow="clipboard-read; clipboard-write"></iframe>
    </div>
</div>

<script type="text/javascript">
    function scrollToAcatFrameBegin() {
        var frameTop = document.getElementById("acat-frame").offsetTop;
        window.scrollTo({top: frameTop, behavior: "smooth"});
    }
    window.addEventListener("message", function (e) {
        try {
            var data = JSON.parse(e.data);
            if (data && data.acatFrameHeight) {
                document.getElementById("acat-frame").style.height = data.acatFrameHeight + "px";
            }
            if (data && data.acatScrollTop) {
                var frameTop = document.getElementById("acat-frame").offsetTop;
                window.scrollTo({top: frameTop + data.acatScrollTop, behavior: "smooth"});
            }
        } catch (e) {}
    }, false);
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>
