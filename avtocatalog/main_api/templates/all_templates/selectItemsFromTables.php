<?php

// /avtocatalog/main_api/templates/all_templates
	
switch ($name) {
	case 'Выбор группы запчастей': ?>

<?php 		

global $DB;	
		
$caption = array();

?>

<!-- /avtocatalog/main_api/templates/all_templates -->
<!-- Выбор группы запчастей -->

<section class="container">
<table data-close="Закрыть" data-additionalinfo="Дополнительная информация" data-brand="">
<tbody>

<tr>
	<? foreach($array_main['data'][0]['tableColumnHeaders'] as $item): ?>

		<th> <?echo $item?></th>

	<? endforeach?>
</tr>



<? foreach($array_main['data'][0]['tableItemFormat'] as $key => $item): ?>

	<?

	$chars_del = ['{', '}'];

	foreach ($item as $key => $value) {

		if(!empty($value['caption'])){
			
			$caption[] = str_replace($chars_del, '', $value['caption']); 

		}

	}


	if(!empty($item[$key])){
	$caption[] = str_replace($chars_del, '', $item['caption']); 
	}


	if(empty($item[$key]) && empty($caption)){
	$caption[] = str_replace($chars_del, '', $item['caption']); 
	}


	?>



<? endforeach?>



	<? foreach($array_main['data'][0]['values'] as $key => $item): ?>


		<tr>

		<?

		$i= 0;

		?>


	<? foreach($item as $key => $value) :?>


		<? if(is_array($value)): ?>

			<? for ($i=0; $i < count($caption) ; $i++) { ?>
					
				<? if($caption[$i] == $key): ?>
						

							<td>


								<? if($key == 'number'): ?>


									<a class="search_by_id" href="/search/?q=<? echo $value[$key]; ?>&function=&VinAction=1"><? echo $value[$key]; ?> <?//echo $arFields['NAME']?></a>


								<? else: ?>
						
									<? echo $value[$key]; ?>

								<?endif?>

							</td>

				<? endif;?>

						


			<? } ?>



		<? else:?>


			<? for ($i=0; $i < count($caption) ; $i++) { ?>
					
				<? if($caption[$i] == $key): ?>

						<? if($caption[$i] == "name" || $caption[$i] == 'modelCode'): ?>

							<td>
								
								<div><? echo $value; ?></div>

							</td>

						<? else:?>

							<td>
						
								<? echo $value; ?>

							</td>

						<? endif;?>

				<? elseif($caption[$i] == 'Открыть'):?>

					<td>
						
						<a href="<? echo $item['url'] ?>">Открыть</a>

					</td>


				<? endif;?>

			<? } ?>

		<? endif?>



	<? endforeach?>


	</tr>


<? endforeach?>


</tbody>
</table>
</section>





		
		<?break;
	
	default:?>






<?php 	

	// Выбор комплектации автомобиля

global $DB;	
		
$caption = array();

?>


<section class="container">
<table data-close="Закрыть" data-additionalinfo="Дополнительная информация" data-brand="">
<tbody>

<tr>
	<? foreach($array_main['data'][0]['tableColumnHeaders'] as $item): ?>

		<th> <?echo $item?></th>

	<? endforeach?>
</tr>



<? foreach($array_main['data'][0]['tableItemFormat'] as $key => $item): ?>

	<?

	$chars_del = ['{', '}'];

	foreach ($item as $key => $value) {

		if(!empty($value['caption'])){
			
			$caption[] = str_replace($chars_del, '', $value['caption']); 

		}

	}


	if(!empty($item[$key])){
	$caption[] = str_replace($chars_del, '', $item['caption']); 
	}


	if(empty($item[$key]) && empty($caption)){
	$caption[] = str_replace($chars_del, '', $item['caption']); 
	}


	?>



<? endforeach?>



	<? foreach($array_main['data'][0]['values'] as $key => $item): ?>


		<tr>

		<?

		$i= 0;

		?>


	<? foreach($item as $key => $value) :?>


		<? if(is_array($value)): ?>

			<? for ($i=0; $i < count($caption) ; $i++) { ?>
					
				<? if($caption[$i] == $key): ?>
						

							<td>


								<? if($key == 'number'): ?>


									<a class="search_by_id" href="/search/?q=<? echo $value[$key]; ?>&function=&VinAction=1"><? echo $value[$key]; ?> <?//echo $arFields['NAME']?></a>


								<? else: ?>
						
									<? echo $value[$key]; ?>

								<?endif?>

							</td>

				<? endif;?>

						


			<? } ?>



		<? else:?>


			<? for ($i=0; $i < count($caption) ; $i++) { ?>
					
				<? if($caption[$i] == $key): ?>

						<? if($caption[$i] == "name" || $caption[$i] == 'modelCode'): ?>

							<td>
								
								<a href="<? echo $item['url'] ?>"><? echo $value; ?></a>

							</td>

						<? else:?>

							<td>
						
								<? echo $value; ?>

							</td>

						<? endif;?>

				<? elseif($caption[$i] == 'Открыть'):?>

					<td>
						
						<a href="<? echo $item['url'] ?>">Открыть</a>

					</td>


				<? endif;?>

			<? } ?>

		<? endif?>



	<? endforeach?>


	</tr>


<? endforeach?>


</tbody>
</table>

</section>



		
		<?break;
}


?>