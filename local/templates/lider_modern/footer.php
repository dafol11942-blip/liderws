</main><!-- /main -->
<?php if (!empty($_GET['return_url'])): ?>
<div style="background:var(--bg);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:16px 0;margin-bottom:0;">
    <div class="container">
        <a href="<?= htmlspecialchars($_GET['return_url']) ?>" class="btn btn--white" style="font-size:14px;">
            ← Назад к подбору запчастей
        </a>
    </div>
</div>
<?php endif; ?>

    <footer class="footer">
        <div class="container footer__grid">
            <div class="footer__col">
                <h4>Каталог</h4>
                <a href="/catalog/masla/">Масла и жидкости</a>
                <a href="/catalog/filtry/">Фильтры</a>
                <a href="/catalog/tormoznye-kolodki/">Тормозные колодки</a>
                <a href="/catalog/grm/">ГРМ и привод</a>
                <a href="/catalog/shiny/">Шины и диски</a>
                <a href="/catalog/akkumulyatory/">Аккумуляторы</a>
            </div>
            <div class="footer__col">
                <h4>Покупателям</h4>
                <a href="/about/">О компании</a>
                <a href="/contacts/">Контакты</a>
                <a href="/delivery/">Доставка и оплата</a>
                <a href="/returns/">Возврат товара</a>
            </div>
            <div class="footer__col">
                <h4>Услуги</h4>
                <a href="/autoservice/">Автосервис</a>
                <a href="/shinomontazh/">Шиномонтаж</a>
                <a href="/tekhosmotr/">Техосмотр</a>
                <a href="/kolesa-darom/">Колеса Даром</a>
            </div>
            <div class="footer__col">
                <h4>Контакты</h4>
                <p><svg class="icon"><use href="#icon-pin"></use></svg> РТ, Елабуга, пр-т Нефтяников, 4</p>
                <p><svg class="icon"><use href="#icon-phone"></use></svg> <a href="tel:+78000000000" style="color:#fff;font-weight:700;">8-800-000-00-00</a></p>
                <p><svg class="icon"><use href="#icon-mail"></use></svg> info@liderws.ru</p>
                <p><svg class="icon"><use href="#icon-clock"></use></svg> Пн-Вс: 9:00–20:00</p>
            </div>
        </div>
        <div class="footer__bottom">
            <div class="container">
                <p>© <?= date('Y') ?> Лидер — магазин автозапчастей в Елабуге. Все права защищены.</p>
            </div>
        </div>
    </footer>

    <script src="<?= SITE_TEMPLATE_PATH ?>/assets/js/main.js"></script>
</body>
</html>
