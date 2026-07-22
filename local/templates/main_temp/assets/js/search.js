// ?vin=JT2AT00N6R0014269&VinAction=Search&language=ru&function=getVin

// search

// search__select-otion

$(document).ready(function() {

    var old_action = $('#search').attr('action');

    var all_selections = $('#select-Vin .select__option');

    var elements = $("#select-Vin .select__options");

    var optionVal = 'detail';

    for (var i = elements.length - 1; i >= 0; i--) {
        elements[i].addEventListener('click',(e) => {
            
            optionVal = $(e.target).data('value');

            var getVin = $('#getVin');

            if(optionVal == 'getVin'){
                
                $('#search').attr('action', '/avtocatalog/');
                // vin.val($('.main-input').val())
                getVin.val('getVin')

            }else{

                $('#search').attr('action',old_action);
                getVin.val('')

            }


        });
    }



    var mob_select = $('.mob-select-in-desctop li');

    for (var i = mob_select.length - 1; i >= 0; i--) {

        mob_select[i].addEventListener('click',(e) => {

            // $('.mob-select li').removeClass('active');
            // $(e.target).addClass('active');

            // $(this).addClass('active');

            
            optionVal = $(e.target).data('search_item');

            var getVin = $('#getVin');

            if(optionVal == 'getVin'){
                
                $('#search').attr('action', '/avtocatalog/');
                // vin.val($('.main-input').val())
                getVin.val('getVin')

            }else{

                $('#search').attr('action',old_action);
                getVin.val('')

            }


        })

    }













    var mob_select = $('.mob-select li');

    for (var i = mob_select.length - 1; i >= 0; i--) {

        mob_select[i].addEventListener('click',(e) => {

            // $('.mob-select li').removeClass('active');
            // $(e.target).addClass('active');

            // $(this).addClass('active');

            
            optionVal = $(e.target).data('search_item');

            var getVin = $('#getVin-mob');

            if(optionVal == 'getVin'){
                
                $('#search-mob').attr('action', '/avtocatalog/');
                // vin.val($('.main-input').val())
                getVin.val('getVin')

            }else{

                $('#search-mob').attr('action',old_action);
                getVin.val('')

            }


        })

    }


        
    


    // })


})