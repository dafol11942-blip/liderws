//Работа с cookie
// Функция для получения значения cookie
function cookieBlockClose(){
    let blockCookie = document.querySelector('.js-cookie-wrapper');
    blockCookie.classList.remove('show');
}
function cookieBlockOpen(){
    let blockCookie = document.querySelector('.js-cookie-wrapper');
    blockCookie.classList.add('show');
}
function getCookie(name) {
    var matches = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}
document.addEventListener('DOMContentLoaded', function() {
    console.log('getCookie = ' + getCookie('cookieBlock'));
    if(getCookie('cookieBlock') === undefined){
        cookieBlockOpen();
    }
});
if(document.querySelector('.js-cookie-close')){
    let cookieClose = document.querySelector('.js-cookie-close');
    cookieClose.addEventListener('click', function(){
        cookieBlockClose();
    });
}
if(document.querySelector('.js-cookie-send')){
    let cookieSend = document.querySelector('.js-cookie-send');
    cookieSend.addEventListener('click', function(){
        cookieBlockClose();
        document.cookie = "cookieBlock=true;max-age=31556926;path=/";
    });
}

$(document).ready(function() {

    $(document).on('click', '[data-action]', function(e){

        var item = $(this).data('action')['show-modal'];

        var $formName = item['name'];

        var title = item['title'];


        if(title){

            var text_change = $('#' + $formName +  ' .modal__title').text(title);

        }


         $('#' + $formName).show();


    })


    $(document).on('click', '.city', function(e){

        var clicked = $(this).data('clicked');

        // let name = $(this).text();

        var $this = $(this);

        $('#modal-select-main-city .modal__field').after('<div></div>');

        e.preventDefault();

        $.ajax({
            type: "POST",
            url: "/include/show_all-main-city.php",
            data: { },
            success: function (msg) {


                var res = JSON.parse(msg);

                // console.log(res);

                if(!clicked){

                    for (var i = res.length - 1; i >= 0; i--) {

                        if($this.text() == res[i]['NAME']) {

                            $('#modal-select-main-city .modal__field > div').after("<li><a href='#' class='active select-city-items' data-id='" + res[i]['ID'] + "'>"+res[i]['NAME']+"</a></li>");

                        }else{

                            $('#modal-select-main-city .modal__field > div').after("<li><a href='#' class='select-city-items' data-id='" + res[i]['ID'] + "'>"+res[i]['NAME']+"</a></li>");
                        }
                        // res[i]
                    }

                }





                // $('#modal-select-city .modal__field').text(res);

                $('#modal-select-main-city').show()
                name = '';
            
            }
        })

        $(this).data('clicked', 1);


        //  CHANGES SELECTED PYNKT

      
        
        $(document).on('click', '.select-city-items', function(e){

            e.preventDefault();

            var id = $(this).data('id');
            // console.log($(this))

            $('.city').text($(this).text());

            // $('#modal-select-main-city').hide()


             $.ajax({
                type: "POST",
                url: "/include/show_all-main-city.php",
                data: {
                    'set_main-city-id':id,
                },
                success: function (msg) {


                    var res = JSON.parse(msg);

                    // console.log(res);

                    $('#modal-select-main-city').hide()

                    location.reload();

                    
                    
                }
            })

        })


       

    })
       


    $("[name='phone']").inputmask("+7(999) 999-9999");
    $("[name='ORDER_PROP_3']").inputmask("+7(999) 999-9999");
    $("[name='ORDER_PROP_21']").inputmask("+7(999) 999-9999");
    // ORDER_PROP_21


    // search__mob-contorls
    $(document).on('click', '.search__mob-contorls .select li', function(e) {

        $('.search__mob-contorls .select li').removeClass('active');

        $(this).addClass('active');

    })

    $(document).on('click', '#addres_change-in-personal' , function (e) {
        $('.cdek_container-personal').show();
    })




    $('.show_city_in_map').click(function(e){

        if($(this).data('id')){

            let id = $(this).data('id');
            e.preventDefault();


            $.ajax({
                type: "GET",
                url: '/include/show_map_by_id.php',
                data: {
                    ajax:1,
                    id : id
                },
                dataType: 'html',
                success: function(result) {

                    $('#modal-map').show();
                    $('#modal-map .modal__open').html(result);
                   

                }
            });

            
        }

        // let name = $(this).data('name');



    });




    // FOR MODAL OPEN

    // $(document).on('click', '.btn', function (e) {

    //     e.preventDefault();

    // })

    $(document).on('click', '.modal-services-detail-btn', function (e) {
        
        e.preventDefault();

        var title = $(this).data('modal-title');
       
        var text_change = $('#modal-service .modal__title').text();

        $('#modal-service .input-service-name').val(title)
        $('#modal-service .modal__title').text(title);
        $('#modal-service').show()

        $('#modal-service .modal__thanks').hide()
        $('#modal-service .modal__open').show()

    })


    // modal-tech-osmotr-btn




    $(document).on('click', '.modal-tech-osmotr-btn', function (e) {

        var modal_name = $(this).data('modal-name');
        $('#modal-service [name="modal-name"]').val(modal_name)


        e.preventDefault();

        var title = $(this).data('modal-name');


        var text_change = $('#modal-service .modal__title').text();

        $('#modal-service .input-service-name').val(title)
        $('#modal-service .modal__title').text(title);
        $('#modal-service').show()

        $('#modal-service .modal__thanks').hide()
        $('#modal-service .modal__open').show()

       
    })



    $(document).on('click', '.modal-services-btn', function (e) {

        e.preventDefault();

        var title = $(this).parent().siblings('.services__title').text();


        var text_change = $('#modal-service .modal__title').text();

        $('#modal-service .input-service-name').val(title)
        $('#modal-service .modal__title').text(title);
        $('#modal-service').show()

        $('#modal-service .modal__thanks').hide()
        $('#modal-service .modal__open').show()

       
    })



    $(document).on('click', '.modal-order-call-btn', function (e) {

        e.preventDefault();

        var title = $(this).text();


        $('#modal-service [name="modal-name"]').val(title)
        var text_change = $('#modal-service .modal__title').text();

        $('#modal-service .modal__title').text(title);
        $('#modal-service').show()

        $('#modal-service .modal__thanks').hide()
        $('#modal-service .modal__open').show()

    })


    $(document).on('click', '.modal-write-our-btn', function (e) {

        e.preventDefault();

        var title = $(this).data('modal-name');
        var modal_name = $(this).data('modal-name');

        // console.log(modal_name)

        $('#modal-write-our [name="modal-name"]').val(modal_name)
        var text_change = $('#modal-service .modal__title').text();
        $('#modal-write-our .modal__title').text(title);
        $('#modal-write-our').show()

        $('#modal-write-our .modal__thanks').hide()
        $('#modal-write-our .modal__open').show()

    })







    $(document).on('click', '.modal__thanks .modal__btn .btn', function (e) {
        $( ".modal" ).hide();
    })


    $(document).on('click', '.modal__close', function(e){
        var $this = $(this);
        $this.parent().parent().parent().hide();
    })


    $(document).on('click', '.modal', function(e){
      
        var $this = $(this)

        var div = $( ".modal-dialog" );

        if ( !div.is(e.target) // если клик был не по нашему блоку
            && div.has(e.target).length === 0 ) { // и не по его дочерним элементам
          $(this).hide(); // скрываем его
        }

        var modal_close = $(this).find('.modal__close');


        modal_close.click(function (e) {

          $this.hide()
          
        })
        // console.log(modal_close)

    })



// FOR MODAL OPEN



    function isEmpty(str) {
        if (str.trim() == '') 
        return true;
        
        return false;
    }

    function scrollMainPage(){
        var counter = 0;
        var $element_scroll = $('.main-banner');

        if ($element_scroll.length) {

        $(window).scroll(function () {

            var scroll = $(window).scrollTop() + $(window).height();
            var offset = $element_scroll.offset().top

            if(counter == 0){
                var preload_ajax = $('.preload_ajax');
                preload_ajax.show();

            }

            if (scroll > offset && counter == 0) {


                counter = 1;

                $.ajax({
                    type: "POST",
                    url: '/avtocatalog/index_for_main.php?function=getBrands',
                    data: {
                        
                        'is_ajax':1,
                    
                    },
                    success: function (msg) {
                    // alert(msg);
                    // console.log(msg)
                    $('#avtovacatalog_lazy').replaceWith(msg)

                          $tabs('.detail-tabs');
                          $cars = $('.cars-tabs');

                          if($cars){

                              $tabs('.cars-tabs');

                          }


                        // console.log('success' + $tabs)



                        // $tabs();

                        var letter_names = $('.car__letter-name-hidden').hide();
                        let cars_Items = $('.cars-item-hidden').hide();


                        // console.log(cars_Items);

                        letter_names.hide();
                        cars_Items.hide();


                        $(document).on('click', '.cars-item__main', function(e) {
                            $('.car__letter').addClass('active');
                            letter_names.show();
                            cars_Items.show();
                            $(this).parent().parent().hide()
                            return false
                        })
                    
                    },
                });

            }


        })

        }

        // MAIN PAGE AVTOVATALOG LAZY LOAD
    }


    scrollMainPage()



    $('.form-query').submit(function(e) {

        e.preventDefault(); 
        var $form = $(this);

        $.ajax({
            type: $form.attr('method'),
            cache: false,
            dataType: 'json',
            data: $form.serialize(),
            url: '/form/',
            success: function(msg) {

                console.log(msg);
                

                if(msg['Error'] == 'N'){

                    $form.parent().hide();
                    $form.parent().siblings('.modal__thanks').show();
                }else{

                    $form.find('.modal__title').html($('<div style="color:red">' + msg['Text'] + '</div>'))
                }


                // $('.modal-question').show();

            }
        });

    });



  $('.ajax-form-modal-question').submit(function(e) {

      e.preventDefault(); 
      var $form = $(this);

     


        $.ajax({
          type: $form.attr('method'),
          cache: false,
          dataType: 'json',
          data: $form.serialize(),
          url: $form.attr('action'),
          success: function(msg) {

            if(msg['Error'] == 'N'){

                $('.modal-question').show();
             
            }else{

              $form.parent().parent().find('.have__question-text').html($('<div style="color:red">' + msg['Text'] + '</div>'))
            }


           

          }
      });

  });

  // AJAX-FORMS




  // ON MAIN PAGE ITEMS SORT



  // ON MAIN PAGE ITEMS SORT












  // FOR CALL IN SELECT

  $(document).on('change', '.phone__select', function(e) {

      var optionSelected = $("option:selected", this);
      var valueSelected = this.value
      document.location.href = valueSelected; 
     
  });

  // FOR CALL IN SELECT













  // ДЛЯ АВТОРИЗАЦИИ РЕГИСТРАЦИИ



  // if(window.location.pathname == "/auth/" || window.location.pathname == "/auth/registration.php"){

  //     $(document).mouseup(function (e) {
  //         var container = $(".forms");
  //         if (container.has(e.target).length === 0){
  //             // container.hide();
  //              document.location.href = '/';
  //         }
  //     });

  // }

  // ДЛЯ АВТОРИЗАЦИИ РЕГИСТРАЦИИ КОНЕЦ




  $(document).on('click', '.product__back', function(e){
       // window.history.back();
       history.go(-1);
      return false;
  })







    let btn__open__orders = $('.open-order-items')

    btn__open__orders.click(function(e){
        $(this).parent().parent().toggleClass('show__border');
        $(this).parent().siblings('.order__item-show').toggle(900);   
        // console.log( $(this).parent());
    })

    $('.car__lang div').click(function(){
        if($(this).hasClass('active')){

        }else {
          $(this).siblings().removeClass('active')
          $(this).addClass('active')
        }
    })


    let is_active = $('.is_active');


    is_active.children('div').click(function(){

      let active__content_parent = $(this).parent().siblings('.is_active-content')

      let active__content = active__content_parent.children('div');


      for(let i = 0; i < active__content.length; i++ ){
          // console.log(active__content[i])

          if($(active__content[i]).hasClass('active')){
              $(active__content[i]).removeClass('active')
              // console.log(2)
          }else {
            $(active__content[i]).addClass('active')
          }
      }

      if($(this).hasClass('active')){

      }else {
        $(this).siblings().removeClass('active')
        $(this).addClass('active')
      }
  })


    var swiper_tumbs = new Swiper(".tumbs__slider", {
      spaceBetween: 10,
      slidesPerView: 4,
      freeMode: true,
      watchSlidesVisibility: true,
      watchSlidesProgress: true,
      allowTouchMove: false,
      direction: 'vertical',
    });


    var main__slider = new Swiper(".main__slider", {
      spaceBetween: 70,
      allowTouchMove: false,
      slidesPerView: 1,
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
      thumbs: {
        swiper: swiper_tumbs,
      },
    });


    let kabinet__change__btn = $('.kabinet__change-btn'); 

    kabinet__change__btn.click( function(e){

      $(this).hide();
      $(this).siblings('.kabinet__change-btn-save').show();
      

      e.preventDefault();
    
      let kabinet__change = $(this).parent().siblings('.kabinet__item').children('.kabinet__change');

      // console.log(kabinet__change)

      for(let i = 0; i < kabinet__change.length; i++){

        let change__text =  $(kabinet__change[i]).children('div').text()

        // console.log(change__text);
          
        let changes__input =  $(kabinet__change[i]).children('input').val(change__text);

      }


      $(this).parents('.change__input').addClass('change');
    })



    





      var $tabs = function (target) {
      var
        _elemTabs = (typeof target === 'string' ? document.querySelector(target) : target),
        _eventTabsShow,
        _showTab = function (tabsLinkTarget) {
          var tabsPaneTarget, tabsLinkActive, tabsPaneShow;

          console.log(tabsLinkTarget)

          if($(tabsLinkTarget).hasClass('tabs__link')){
            
            tabsPaneTarget = document.querySelector(tabsLinkTarget.getAttribute('href'));

          }

          // if(document.querySelector(tabsLinkTarget).hasClass('tabs__link')){
            

          // }


          tabsLinkActive = tabsLinkTarget.parentElement.querySelector('.tabs__link_active');
          tabsPaneShow = tabsPaneTarget.parentElement.querySelector('.tabs__pane_show');
          // если следующая вкладка равна активной, то завершаем работу
          if (tabsLinkTarget === tabsLinkActive) {
            return;
          }
          // удаляем классы у текущих активных элементов
          if (tabsLinkActive !== null) {
            tabsLinkActive.classList.remove('tabs__link_active');
          }
          if (tabsPaneShow !== null) {
            tabsPaneShow.classList.remove('tabs__pane_show');
          }
          // добавляем классы к элементам (в завимости от выбранной вкладки)
          tabsLinkTarget.classList.add('tabs__link_active');
          tabsPaneTarget.classList.add('tabs__pane_show');
          document.dispatchEvent(_eventTabsShow);
        },
        _switchTabTo = function (tabsLinkIndex) {
          var tabsLinks = _elemTabs.querySelectorAll('.tabs__link');
          if (tabsLinks.length > 0) {
            if (tabsLinkIndex > tabsLinks.length) {
              tabsLinkIndex = tabsLinks.length;
            } else if (tabsLinkIndex < 1) {
              tabsLinkIndex = 1;
            }
            _showTab(tabsLinks[tabsLinkIndex - 1]);
          }
        };
      
      _eventTabsShow = new CustomEvent('tab.show', { detail: _elemTabs });

      if(_elemTabs){

        _elemTabs.addEventListener('click', function (e) {
          var tabsLinkTarget = e.target;
          // завершаем выполнение функции, если кликнули не по ссылке
          if (!tabsLinkTarget.classList.contains('tabs__link')) {
            return;
          }
          // отменяем стандартное действие
          e.preventDefault();
          _showTab(tabsLinkTarget);
        });


      }

     
      
      return {
        showTab: function (target) {
          _showTab(target);
        },
        switchTabTo: function (index) {
          _switchTabTo(index);
        }
      }
      
      };


  // Запус табов  
      


      // Avtocatlog-main-page

      var letter_names = $('.car__letter-name-hidden').hide();
      let cars_Items = $('.cars-item-hidden').hide();


      // console.log(cars_Items);

      letter_names.hide();
      cars_Items.hide();


      $(document).on('click', '.cars-item__main', function(e) {
          $('.car__letter').addClass('active');
          letter_names.show();
          cars_Items.show();
          $(this).parent().parent().hide()
          return false
      })

      // Avtocatlog-main-page




      // $('.search__select').select2();
      // $('.phone__select').select2();

      let menu__btn = $('.mob__menu-btn');

      let mob__search  = $('.mob__search');

      let menu__content = $('.mob__menu-content');


      let btn__search = $('.mob__search');
      let search__input = $('.mob__search-main');
      let search__mob__contorls = $('.search__mob-contorls-mob');
      let search__close = $('.search__close')

      menu__btn.click(function(e){

          if($(this).hasClass('active')){

              $(this).removeClass('active');
              mob__search.removeClass('hidden');
              menu__content.addClass('hidden');
              $('body').removeClass('scroll');

          }else {

              $(this).addClass('active');
              mob__search.addClass('hidden');
              menu__content.removeClass('hidden');
              $('body').addClass('scroll');

              // Закрывать поиск(если открытый)
              close__search();
              btn__search.addClass('hidden')
            
             

          }
      })

      btn__search.click(function(e){
          $(this).addClass('hidden')
          search__input.removeClass('hidden')
          search__mob__contorls.removeClass('hidden')
          $('body').addClass('search_body');
      })

      search__close.click(close__search);

      function close__search(){

          search__mob__contorls.addClass('hidden')
          search__input.addClass('hidden')
          btn__search.removeClass('hidden')
          $('body').removeClass('search_body');

      }


      $(document).mouseup(function (e){ // событие клика по веб-документу
        var div = $(".mob__header"); // тут указываем ID элемента
        if (!div.is(e.target) // если клик был не по нашему блоку
            && div.has(e.target).length === 0) { // и не по его дочерним элементам
              close__search();
        }
      });














  const main__banner = new Swiper('.main-banner__inner', {
      loop: true,
      autoplay: {
        delay: 10000,
        disableOnInteraction: false,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
   
      pagination: {
        el: '.swiper-pagination',
        type: 'bullets',
      },

    });
    
  // console.log($('.main-banner__inner'))




  $tabs('.detail-tabs');
  $cars = $('.cars-tabs');

  if($cars){

      $tabs('.cars-tabs');

  }


  // let checkbox__all = $('input[name="select__form"]')



  chekbox__check_radio('input[name="UF_SELECT_FORM"]')

  function chekbox__check_radio(all){
      let checkbox__all = $(all)
      checkbox__all.each(function (index, element) {
          let element__id = $(element).data('val')
          let main__element = $('#'+ element__id);
          // main__element.removeClass('active')
          $(element).click(()=> {
              $(main__element).siblings().removeClass('active')
              if($(element).is(":checked")){
                  main__element.addClass('active')
              }
          })  
      
      })
  }

  active__class('.ft');

  function active__class(elements){

      all__items = $(elements);

      all__items.each(function (index, element){

          $(element).click( () => {
              // console.log(element)
              all__items.each(function (index, remove_active){ 
                  $(remove_active).removeClass('active')
              })

              $(this).addClass('active')

          })

      })
  }

  check_btn('.ft')

  function check_btn(all){
      let checkbox__all = $(all)
      checkbox__all.each(function (index, element) {
          let element__id = $(element).data('val') //получаем значени data-val
          let main__element = $('#'+ element__id); // ищем по нему id
         
          $(element).click(()=> {
              $(main__element).siblings().removeClass('active') 
              if($(element).hasClass('active')){
                  main__element.addClass('active')
              }
          })  
      
      })
  }




  // console.log(checkbox__all)

  // var listTabs = document.querySelectorAll('.cars-tabs');
  // for (var i = 0, length = listTabs.length; i < length; i++) {
  // $tabs(listTabs[i]);
  // }     



  //  

  // MOB FILTER OPEN 



  //   $(document).on('input', '.main-input', function(e) {
  //
  //       let header_text = $('.header-item__search-text')
  //       let elem_1 = $('.header-item__search-line')
  //       let elem_2 = $('.header-item__search-select')
  //
  //       // console.log($(this).val().length);
  //
  //       if($(this).val().length > 0){
  //           header_text.fadeOut(300);
  //           elem_1.fadeOut(300);
  //           elem_2.fadeOut(300);
  //       }else {
  //           header_text.fadeIn(300);
  //           elem_1.fadeIn(300);
  //           elem_2.fadeIn(300);
  //       }
  //
  // })


});

// $('.forms__registr').click(function(){
//   $.arcticmodal({
//       type: 'ajax',
//       url: '/auth/registration.php',
//       afterLoading: function(data, el) {
//           alert('afterLoading');
//       },
//       afterLoadingOnShow: function(data, el) {
//           alert('afterLoadingOnShow');
//       }
//   });
// })

// $('.forms__login ').click(function(){

//   $.arcticmodal({
//       type: 'ajax',
//       url: '/auth/authorization.php',
//       afterLoading: function(data, el) {
//           alert('afterLoading');
//       },
//       afterLoadingOnShow: function(data, el) {
//           alert('afterLoadingOnShow');
//       }
//   });

// })




// for (var i = viewtypes.length - 1; i >= 0; i--) {

//     $(viewtypes[i]).on( "click", function(e) {

//         e.preventDefault();


//         let viewtype = $(this).data('viewtypes');

//         console.log(viewtype );

//     });

// }