$(document).ready(function() {


    function number_format(number, decimals, dec_point, separator ) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
          prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
          sep = (typeof separator === 'undefined') ? ' ' : separator ,
          dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
          s = '',
          toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + (Math.round(n * k) / k)
              .toFixed(prec);
          };
        // Фиксим баг в IE parseFloat(0.55).toFixed(0) = 0;
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
          .split('.');
        if (s[0].length > 3) {
          s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '')
          .length < prec) {
          s[1] = s[1] || '';
          s[1] += new Array(prec - s[1].length + 1)
            .join('0');
        }
        return s.join(dec) + " ₽";
    }

 // if elemnts in catalog a lot of 10, change the 10 count 

    $(document).on('click', '.filter__plus', function (e) {

        var box = $(this).parent().siblings('.box__tab')

        var sub_items = box.find('.box__content')

        var count = 10;


        if(sub_items.length > count){

            // var count = 10;

            for(var j = 0; j < sub_items.length; j++){

                if(j > count){

                    $(sub_items[j]).addClass('hidden');

                }

            }

            var last_sub_item = sub_items.length - 1;
            
            var showAll = $(sub_items[last_sub_item]).after("<div class='box__tab-showAll'>показать еще<div");



            $(document).on('click', '.box__tab-showAll' , function(e) {

                var hidden_items = $(this).siblings('.hidden');

                // var count = 10;

                for(var j = 0; j < hidden_items.length; j++){

                    if(j < count){

                        // $(hidden_items[j]).show();
                        $(hidden_items[j]).removeClass('hidden')

                    }


                }

                if(hidden_items.length < count){
                    $(this).hide();
                }

                
            })


        }
        
    })


    // for catalog height
    function items_height_auto(argument) {
        if ($('.product').hasClass('vibor_model')) {

          // console.log("SUSSEC")

          var items = $('.vibor_model .product__item');
          var items_height = 20;


        }else if($('.product').hasClass('cart__catalog')){
          return;
        }
        else{

          var items = $('.product__inner .product__item');
          var items_height = 7;
        }


        for (var i = items.length - 1; i >= 0; i--) {
          

          var length =  $(items[i]).find('.product__hover-item').length;

          var height_item = $(items[i]).find('.product__hover-item').height()

          var height = 5;

          var calc = height + (length * items_height);

          $(items[i]).find('.product__hover').css('height',calc + "%");

        }
        
    }

    items_height_auto();



    // catalof grid or list
    let viewtypes = $('.viewtype');

    viewtypes.click(function(e){

        $('.viewtype').removeClass('active')
        $(this).addClass('active')

        
          e.preventDefault()

          let href = $(this).attr('href');

           let viewtype = $(this).data('viewtypes');

          // let sort_field = $(this).data('sort');

          // let sort_order = $(this).data('method');
          
          // console.log(href)

          $.ajax({
              type: "POST",
              url: href,
              data: {
                  "viewtype": viewtype,
                  'is_ajax':1,
                  // 'ELEMENT_SORT_FIELD': sort_field,
                  // 'ELEMENT_SORT_ORDER': sort_order
                // PRODUCT_ID: ID,
                // QUANTITY: 1,
              },
              success: function (msg) {
                // alert(msg);
                $('.product__inner').replaceWith(msg)
               
              },
          });
    })



    let sort_by_catalog = $('#sort_by_catalog')

    sort_by_catalog.on('change', function(e){

          let href = $(this).attr('href');

          // console.log( $('option:checked', this).data('sort'))

          let sort_field = $('option:checked', this).data('sort');

          let sort_order = $('option:checked', this).data('method');
          
          // console.log(href)

        $.ajax({
            type: "POST",
            url: href,
            data: {
               // "viewtype": viewtype,
              'is_ajax':1,
              'ELEMENT_SORT_FIELD': sort_field,
              'ELEMENT_SORT_ORDER': sort_order
            // PRODUCT_ID: ID,
            // QUANTITY: 1,
            },
            success: function (msg) {
            // alert(msg);
            console.log(msg)
            $('.product__inner').replaceWith(msg)

            },
        });
    })


    $(document).on('click', '.item__plus', function(e){


        e.preventDefault();
        let input = $(this).siblings('.item__number');

        // console.log(input)

        let id = input.attr('id');

        let first_val = Number(input.val())

        input.val(first_val + 1)

        if($(this).hasClass('add_to_basket')){

          add_to_basket__ajax_fun(id);
        }

    })


    $(document).on('click', '.remove__cart', function(e){
        e.preventDefault();
        let id = $(this).data('id');

        $(this).parent().hide();

        delete_from_basket__ajax_fun_all(id);

    })

    $(document).on('click', '.item__minus', function(e){

        e.preventDefault();

        var page = $(this).data('page');



        let input = $(this).siblings('.item__number');


        let first_val = Number(input.val())


        if(input.val() > 1 || input.val() == 1) {
            
            input.val(first_val - 1)

        }


        let id = input.attr('id');


        if(input.val() < 1) {



            // ВЫКЮЧАЕМ ССЫЛКУ В КОРЗИНУ 

            $(this).parent().siblings('.in_cart').hide();


            if(page != 'detail'){

              $(this).parent().hide();


              $(this).parent().siblings('.product-item-button-container').show();
              $(this).parent().siblings('.product-item-button-container').children().show();

            }


              if(window.location.pathname == "/cart/"){
                  $(this).parent().parent().parent().hide()
              }

             delete_from_basket__ajax_fun_all(id);
         
      }else{
        delete_from_basket__ajax_fun(id);
      }

    })


  // IN DETAIL 

    let add_to_basket__ajax = $('.detail-page-add-to-cart');

    add_to_basket__ajax.click(function(e){

          e.preventDefault();

          var count = $(this).siblings('.product__count').children('input').val()

          console.log(count)

          var id = this.id

          // console.log(id)

          add_to_basket__ajax_fun(id,count);

          // $(this).id

    }) 




    //  ПЕРВЫЙ КЛИК ПО КУПИТЬ, ДОБАВЛЕНИЕ В КОРЗИНУ

    $(document).on('click', '.add_to_basket__ajax-first', function(e){

          e.preventDefault();

          // console.log($(this))

          var id = this.id

          add_to_basket__ajax_fun(id);

          $(this).parent().siblings('.product__count').children('input').val(1)

          $(this).parent().siblings('.product__count').show()

          $(this).hide();



          // ВЛКЮЧАЕМ ССЫЛКУ В КОРЗИНУ 

          $(this).parent().siblings('.in_cart').show()

    }) 



    function add_to_basket__ajax_fun(ID, count = 1) {
        $.ajax({
          type: "POST",
          url: "/include/add_to_basket__ajax.php",
          data: {
            PRODUCT_ID: ID,
            QUANTITY: count,
          },
          success: function (msg) {
                // alert(msg);
                // $('.bg-blue .header-item__text-bottom').each(function () {
                //    $(this).prop('Counter', Number($(this).text())).animate({
                //     Counter: msg
                //     }, {
                //      duration: 1000,
                //      easing: 'swing',
                //      step: function (now) {
                //         $(this).text(number_format(Math.ceil(now)) ,2, '.', ' ');
                //      }
                //     });
                // });

                // $('.count-main').each(function () {
                //    $(this).prop('Counter', Number($(this).text())).animate({
                //     Counter: msg
                //     }, {
                //      duration: 1000,
                //      easing: 'swing',
                //      step: function (now) {
                //        $(this).text(number_format(Math.ceil(now)) ,2, '.', ' ');
                //         // $(this).text(Math.ceil(now));
                //      }
                //     });
                // });

                
                $('.bg-blue .header-item__text-bottom').html(number_format(Math.ceil(msg) ));
                $('.count-main').html(number_format(Math.ceil(msg) ));


          },
        });
    }


    function delete_from_basket__ajax_fun_all(ID) {
        $.ajax({
          type: "POST",
          url: "/include/add_to_basket__ajax.php",
          data: {
            PRODUCT_ID: ID,
            ajaxAction : 'delete_all',
          },
          success: function (msg) {
            // alert(msg);
            $('.bg-blue .header-item__text-bottom').html(number_format(Math.ceil(msg) ))
            $('.count-main').html(number_format(Math.ceil(msg) ))

            // $('.bg-blue .header-item__text-bottom').each(function () {
            //    $(this).prop('Counter', Number($(this).text())).animate({
            //     Counter: msg
            //     }, {
            //      duration: 1000,
            //      easing: 'swing',
            //      step: function (now) {
            //          $(this).text(number_format(Math.ceil(now)) ,2, '.', ' ');
            //      }
            //     });
            // });


            // $('.count-main').each(function () {
            //    $(this).prop('Counter', Number($(this).text())).animate({
            //     Counter: msg
            //     }, {
            //      duration: 1000,
            //      easing: 'swing',
            //      step: function (now) {
            //         $(this).text(number_format(Math.ceil(now)) ,2, '.', ' ');
            //      }
            //     });
            // });



          },
        });
    }

    function delete_from_basket__ajax_fun(ID) {
        $.ajax({
          type: "POST",
          url: "/include/add_to_basket__ajax.php",
          data: {
            PRODUCT_ID: ID,
            ajaxAction : 'delete',
          },
          success: function (msg) {
            // alert(msg);
            $('.bg-blue .header-item__text-bottom').html(number_format(Math.ceil(msg) ))
            $('.count-main').html(number_format(Math.ceil(msg) ))

            // $('.bg-blue .header-item__text-bottom').each(function () {
            //    $(this).prop('Counter', Number($(this).text())).animate({
            //     Counter: msg
            //     }, {
            //      duration: 1000,
            //      easing: 'swing',
            //      step: function (now) {
            //          $(this).text(number_format(Math.ceil(now)) ,2, '.', ' ');
            //      }
            //     });
            // });


            // $('.count-main').each(function () {
            //    $(this).prop('Counter', Number($(this).text())).animate({
            //     Counter: msg
            //     }, {
            //      duration: 1000,
            //      easing: 'swing',
            //      step: function (now) {
            //         $(this).text(number_format(Math.ceil(now)) ,2, '.', ' ');
            //      }
            //     });
            // });



          },
        });
    }


    let all_filter_box = $('.filter__box');





    for(let i = 0; i < all_filter_box.length; i++){

      let box_table = $(all_filter_box[i]).children('.box__table');

      let filter__btns = box_table.children('.filter__btns')

      if($(all_filter_box).hasClass('zapchasti-open')){

        // FOR ZAPSATI AVTOCATALOG

            filter__btns = $(all_filter_box[i]);

      }

      filter__btns.click(function(e){

        

            let $this = $(this).parent();

             if($(all_filter_box).hasClass('zapchasti-open')){

                // FOR ZAPSATI AVTOCATALOG

                $this = box_table;

               $($this).children(1).children('.filter__plus').toggle(200)
               $($this).children(1).children('.filter__minus').toggle(200)

            }




            let box_content = $($this).siblings('.box__tab');
            box_content.toggle(200);
            $($this).children('.filter__plus').toggle()
            $($this).children('.filter__minus').toggle()


            


         
      })
    }

    $('.filter__reset').click(function(){
      $('#filter')[0].reset();
    })


    if(document.documentElement.clientWidth < 992){

        let mob__filter_btn = $('.filter__box-mob-open')

        let product__fulter = $('.product__fulter')

        mob__filter_btn.click( function (){
          $(this).hide(400)
          product__fulter.show(400);
        })

        let  mob__filter_btn_close = $('.filter__box-mob-close');


        mob__filter_btn_close.click(function (){
          product__fulter.hide(400)
          mob__filter_btn.show(400);
        })

    }

    $(document).on('click', '.select_city_in_detail_page', function(e){

        var clicked = $(this).data('clicked');

        $('#modal-select-city .modal__field').after('<div></div>');

        e.preventDefault();

        $.ajax({
            type: "POST",
            url: "/include/show_all_city.php",
            data: { },
            success: function (msg) {


                var res = JSON.parse(msg);

                if(!clicked){

                    for (var i = res.length - 1; i >= 0; i--) {
                        // res[i]
                        $('#modal-select-city .modal__field > div').after("<li><a href='#' class='select-city-items' data-id='" + i + "'>"+res[i]+"</a></li>");
                    }

                }




                // $('#modal-select-city .modal__field').text(res);

                $('#modal-select-city').show()
            
            }
        })

        $(this).data('clicked', 1);


        //  CHANGES SELECTED PYNKT

        
        $(document).on('click', '.select-city-items', function(e){

            e.preventDefault();
            // console.log($(this))

            $('.selected_city_detail').text($(this).text());

            $('#modal-select-city').hide()

        })


       

    })
          


    // select_city_in_detail_page


});