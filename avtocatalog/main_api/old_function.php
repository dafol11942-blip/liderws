<? function main_data_select_сomplectation($array_main, $format, $name='standart'){ ?>


	<?

		// debug($array_main);


		debug($array_main);

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
		    				<!-- <th>Трансмиссия</th>
		    				<th>Модельный год</th>
		    				<th>
		    				</th> -->
		    			</tr>
		    			<!-- <tr> -->
		    			<? //foreach($array_main['data'][0]['values'] as $key => $item): ?>

		    		
		    					<!-- <tr> -->
		    				<? foreach($array_main['data'][0]['tableItemFormat'] as $key => $item): ?>

		    					
			    					<?


			    						
			    						$chars_del = ['{', '}'];

			    						// if($item[$key])



			    						 foreach ($item as $key => $value) {

			    						 	// debug($value);
			    						 		if(!empty($value['caption'])){
			    						 			
			    						 			$caption[] = str_replace($chars_del, '', $value['caption']); 

			    						 		}


			    							# code...


			    						}

			    						 // debug($item[$key]);

			    						 if(!empty($item[$key])){
			    						 	$caption[] = str_replace($chars_del, '', $item['caption']); 
			    						 }


			    						if(empty($item[$key]) && empty($caption)){
			    							$caption[] = str_replace($chars_del, '', $item['caption']); 
			    						}

			    						// echo $caption;

			    					?>
				    				


		    				<? endforeach?>

		    				<?

		    				 // debug($caption);

		    				?>

		    				
		    				<? foreach($array_main['data'][0]['values'] as $key => $item): ?>

		    					<?// debug($item);?>

		    				
		    					<tr>

		    						<?

		    						$i= 0;

		    						?>


		    						

		    						<? foreach($item as $key => $value) :?>

		    							<?
		    								  // debug($value);

		    							?>

		    							<? if(is_array($value)): ?>

		    								<? for ($i=0; $i < count($caption) ; $i++) { ?>
				    								
				    							<? if($caption[$i] == $key): ?>
				    									

					    									<td>

					    										<!-- ЕСЛИ элемент продукт, то  -->

					    										<? if($key == 'number'): ?>

					    											<?
																		// $arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM","DETAIL_PAGE_URL");
																		// $arFilter = Array("IBLOCK_ID"=>36, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y", 'PROPERTY_CML2_ARTICLE' =>$value[$key]);
																		// $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);
																		// while($ob = $res->GetNextElement())
																		// {
																		//  $arFields = $ob->GetFields();

																		//  //print_r($arFields);
																		// }


																		// $res = $DB->Query("SELECT `IBLOCK_ELEMENT_ID` FROM `b_iblock_element_property` WHERE `VALUE` = '" . $value[$key] . "' LIMIT 1");

																		// while ($rows = $res->Fetch()) {
																		//     // debug($rows);
																		//     // echo "111";
																		//     $id_item = $rows['IBLOCK_ELEMENT_ID'];

																		//     // debug($id_item);

																		// 	$el_res = CIBlockElement::GetByID( $id_item );

																		// 	if( $el_arr= $el_res->GetNext() ) {
																		// 		$detail_url = $el_arr['DETAIL_PAGE_URL'];
																		// 		$arFields['NAME'] = $el_arr['NAME'];
																		// 	}

																		// }






																		?> 

					    											<!-- Нужно найти здесь элемент с этим айди -->

					    											<a class="search_by_id" href="/search/?q=<? echo $value[$key]; ?>&function=&VinAction=1"><? echo $value[$key]; ?> <?//echo $arFields['NAME']?></a>

					    											<? 
					    											// unset($arFields);
					    											// unset($detail_url);
					    											?>

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








	<? } ?>