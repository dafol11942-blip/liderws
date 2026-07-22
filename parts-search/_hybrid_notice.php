<?php 
$alreadyVerified = isset($_GET['verified']);
if (!empty($verifyTaskHash)): 
?>
<div class="instant-notice" id="instant-notice" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin:0 0 12px;font-size:14px;display:flex;align-items:center;gap:10px;">
<span style="font-size:20px;">⚡</span>
<span>Мгновенная выдача: <?= count($cachedItems ?? []) ?> складов, <?= number_format($instantCacheMs, 1, ',', ' ') ?> мс. 
<span id="verify-status" style="color:#0066ff;">Догружаем предложения от всех поставщиков...</span></span>
</div>
<script>
(function(){
<?php if (!$alreadyVerified): ?>
var h="<?=$verifyTaskHash?>", s=document.getElementById("verify-status"), n=document.getElementById("instant-notice"), c=0;
function p(){c++;fetch("/local/php_interface/ajax/verify_poll.php?task_hash="+h).then(r=>r.json()).then(d=>{
if(d.status==="done"){
    s.innerHTML="✅ Все поставщики опрошены, обновляем...";
    n.style.background="#f0fdf4";
    // Один раз перезагружаем с флагом verified=1 чтобы не зациклиться
    var u=new URL(window.location.href);
    u.searchParams.set("verified","1");
    window.location.href=u.toString();
}else if(c>=45){
    s.innerHTML='⏱️ <a href="'+window.location.href+'" style="color:#0066ff;">Обновить страницу</a>';
    n.style.background="#fff7ed";n.style.border="1px solid #fed7aa";
}else{setTimeout(p,1000);}
}).catch(function(){if(c<45)setTimeout(p,1000);else s.innerHTML="⏱️ Затянулось";});}
var b="task_hash="+h+"&article=<?=urlencode($displayArticle ?? '')?>&brand=<?=urlencode($displayBrand ?? '')?>&brandMap=<?=urlencode(json_encode($cachedBrandMap ?? [], JSON_UNESCAPED_UNICODE))?>&exactKey=<?=urlencode($exactKey ?? '')?>&targetEntry=<?=urlencode(json_encode($targetEntry ?? null, JSON_UNESCAPED_UNICODE))?>";
fetch("/local/php_interface/ajax/verify_start.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b}).then(function(){setTimeout(p,1500);}).catch(function(){s.innerHTML="⚠️ Ошибка";});
<?php else: ?>
// Уже проверено — показываем статус
document.getElementById("verify-status").innerHTML="✅ Данные актуальны";
document.getElementById("instant-notice").style.background="#f0fdf4";
<?php endif; ?>
})();
</script>
<?php endif; ?>
