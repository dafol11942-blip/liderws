"use strict";

// $( function() {
$('.order__item-show').hide();
var btn__open__orders = $('.open-order-items');
btn__open__orders.click(function (e) {
  $(this).parent().parent().toggleClass('show__border');
  $(this).parent().siblings('.order__item-show').toggle(900); // console.log( $(this).parent());
});
$('.car__lang div').click(function () {
  if ($(this).hasClass('active')) {} else {
    $(this).siblings().removeClass('active');
    $(this).addClass('active');
  }
});
var is_active = $('.is_active');
is_active.children('div').click(function () {
  var active__content_parent = $(this).parent().siblings('.is_active-content');
  var active__content = active__content_parent.children('div');

  for (var i = 0; i < active__content.length; i++) {
    console.log(active__content[i]);

    if ($(active__content[i]).hasClass('active')) {
      $(active__content[i]).removeClass('active'); // console.log(2)
    } else {
      $(active__content[i]).addClass('active');
    }
  }

  if ($(this).hasClass('active')) {} else {
    $(this).siblings().removeClass('active');
    $(this).addClass('active');
  }
});
var swiper_tumbs = new Swiper(".tumbs__slider", {
  spaceBetween: 10,
  slidesPerView: 4,
  freeMode: true,
  watchSlidesVisibility: true,
  watchSlidesProgress: true,
  allowTouchMove: false,
  direction: 'vertical'
});
var main__slider = new Swiper(".main__slider", {
  spaceBetween: 10,
  allowTouchMove: false,
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev"
  },
  thumbs: {
    swiper: swiper_tumbs
  }
});
var kabinet__change__btn = $('.kabinet__change-btn');
kabinet__change__btn.click(function (e) {
  $(this).hide();
  $(this).siblings('.kabinet__change-btn-save').show();
  e.preventDefault();
  var kabinet__change = $(this).parent().siblings('.kabinet__item').children('.kabinet__change');
  console.log(kabinet__change);

  for (var i = 0; i < kabinet__change.length; i++) {
    var change__text = $(kabinet__change[i]).children('div').text();
    console.log(change__text);
    var changes__input = $(kabinet__change[i]).children('input').val(change__text);
  }

  $(this).parents('.change__input').addClass('change');
});

var $tabs = function $tabs(target) {
  var _elemTabs = typeof target === 'string' ? document.querySelector(target) : target,
      _eventTabsShow,
      _showTab = function _showTab(tabsLinkTarget) {
    var tabsPaneTarget, tabsLinkActive, tabsPaneShow;
    tabsPaneTarget = document.querySelector(tabsLinkTarget.getAttribute('href'));
    tabsLinkActive = tabsLinkTarget.parentElement.querySelector('.tabs__link_active');
    tabsPaneShow = tabsPaneTarget.parentElement.querySelector('.tabs__pane_show'); // если следующая вкладка равна активной, то завершаем работу

    if (tabsLinkTarget === tabsLinkActive) {
      return;
    } // удаляем классы у текущих активных элементов


    if (tabsLinkActive !== null) {
      tabsLinkActive.classList.remove('tabs__link_active');
    }

    if (tabsPaneShow !== null) {
      tabsPaneShow.classList.remove('tabs__pane_show');
    } // добавляем классы к элементам (в завимости от выбранной вкладки)


    tabsLinkTarget.classList.add('tabs__link_active');
    tabsPaneTarget.classList.add('tabs__pane_show');
    document.dispatchEvent(_eventTabsShow);
  },
      _switchTabTo = function _switchTabTo(tabsLinkIndex) {
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

  _eventTabsShow = new CustomEvent('tab.show', {
    detail: _elemTabs
  });

  if (_elemTabs) {
    _elemTabs.addEventListener('click', function (e) {
      var tabsLinkTarget = e.target; // завершаем выполнение функции, если кликнули не по ссылке

      if (!tabsLinkTarget.classList.contains('tabs__link')) {
        return;
      } // отменяем стандартное действие


      e.preventDefault();

      _showTab(tabsLinkTarget);
    });
  }

  return {
    showTab: function showTab(target) {
      _showTab(target);
    },
    switchTabTo: function switchTabTo(index) {
      _switchTabTo(index);
    }
  };
}; // Запус табов  


$(document).ready(function () {
  // $('.search__select').select2();
  // $('.phone__select').select2();
  var menu__btn = $('.mob__menu-btn');
  var mob__search = $('.mob__search');
  var menu__content = $('.mob__menu-content');
  var btn__search = $('.mob__search');
  var search__input = $('.mob__search-main');
  var search__mob__contorls = $('.search__mob-contorls');
  var search__close = $('.search__close');
  menu__btn.click(function (e) {
    if ($(this).hasClass('active')) {
      $(this).removeClass('active');
      mob__search.removeClass('hidden');
      menu__content.addClass('hidden');
      $('body').removeClass('scroll');
    } else {
      $(this).addClass('active');
      mob__search.addClass('hidden');
      menu__content.removeClass('hidden');
      $('body').addClass('scroll'); // Закрывать поиск(если открытый)

      close__search();
      btn__search.addClass('hidden');
    }
  });
  btn__search.click(function (e) {
    $(this).addClass('hidden');
    search__input.removeClass('hidden');
    search__mob__contorls.removeClass('hidden');
    $('body').addClass('search_body');
  });
  search__close.click(close__search);

  function close__search() {
    search__mob__contorls.addClass('hidden');
    search__input.addClass('hidden');
    btn__search.removeClass('hidden');
    $('body').removeClass('search_body');
  }

  $(document).mouseup(function (e) {
    // событие клика по веб-документу
    var div = $(".mob__header"); // тут указываем ID элемента

    if (!div.is(e.target) // если клик был не по нашему блоку
    && div.has(e.target).length === 0) {
      // и не по его дочерним элементам
      close__search();
    }
  });
});
var all_filter_box = $('.filter__box');

for (var i = 0; i < all_filter_box.length; i++) {
  var box_table = $(all_filter_box[i]).children('.box__table');
  var filter__btns = box_table.children('.filter__btns');
  filter__btns.click(function (e) {
    var $this = $(this).parent();
    var box_content = $($this).siblings('.box__tab');
    box_content.toggle(200);
    $($this).children('.filter__plus').toggle();
    $($this).children('.filter__minus').toggle();
  });
}

$('.filter__reset').click(function () {
  $('#filter')[0].reset();
});
var price_placeholder_max = $('#price_to').val();
var slider_range = $('#slider-range');

if (slider_range) {
  $("#slider-range").slider({
    range: true,
    min: 0,
    max: parseInt(price_placeholder_max),
    step: 10,
    slide: function slide(event, ui) {
      $("#rub-left").text(ui.values[0] + ''); // текст левого span

      $("#rub-right").text(ui.values[1] + ''); // текст правого span

      $("#price_from").val(ui.values[0]);
      $("#price_to").val(ui.values[1]);

      if (ui.handleIndex === 0) {// потянули левый ползунок - переместим левый span
        // $("#rub-left").css('margin-left', ui.handle.style.left);
      } else {// потянули правый ползунок - переместим правый span
          // $("#rub-right").css('margin-left', ui.handle.style.left);
        }
    }
  });
}

var main__banner = new Swiper('.main-banner__inner', {
  loop: true,
  navigation: {
    nextEl: '.swiper-button-next',
    prevEl: '.swiper-button-prev'
  },
  pagination: {
    el: '.swiper-pagination',
    type: 'bullets'
  }
});
-$tabs('.detail-tabs');
$cars = $('.cars-tabs');

if ($cars) {
  $tabs('.cars-tabs');
} // let checkbox__all = $('input[name="select__form"]')


chekbox__check_radio('input[name="select__form"]');

function chekbox__check_radio(all) {
  var checkbox__all = $(all);
  checkbox__all.each(function (index, element) {
    var element__id = $(element).data('val');
    var main__element = $('#' + element__id); // main__element.removeClass('active')

    $(element).click(function () {
      $(main__element).siblings().removeClass('active');

      if ($(element).is(":checked")) {
        main__element.addClass('active');
      }
    });
  });
}

active__class('.ft');

function active__class(elements) {
  all__items = $(elements);
  all__items.each(function (index, element) {
    var _this = this;

    $(element).click(function () {
      console.log(element);
      all__items.each(function (index, remove_active) {
        $(remove_active).removeClass('active');
      });
      $(_this).addClass('active');
    });
  });
}

check_btn('.ft');

function check_btn(all) {
  var checkbox__all = $(all);
  checkbox__all.each(function (index, element) {
    var element__id = $(element).data('val'); //получаем значени data-val

    var main__element = $('#' + element__id); // ищем по нему id

    $(element).click(function () {
      $(main__element).siblings().removeClass('active');

      if ($(element).hasClass('active')) {
        main__element.addClass('active');
      }
    });
  });
} // console.log(checkbox__all)
// var listTabs = document.querySelectorAll('.cars-tabs');
// for (var i = 0, length = listTabs.length; i < length; i++) {
// $tabs(listTabs[i]);
// }     
//  
// MOB FILTER OPEN 


var mob__filter_btn = $('.filter__box-mob-open');
var product__fulter = $('.product__fulter');
mob__filter_btn.click(function () {
  $(this).hide(400);
  product__fulter.show(400);
});
var mob__filter_btn_close = $('.filter__box-mob-close');
mob__filter_btn_close.click(function () {
  product__fulter.hide(400);
  mob__filter_btn.show(400);
});