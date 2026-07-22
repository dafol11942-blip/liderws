$(document).ready(function () {

    // $('#location-search').

    // $("#location-search").on("keyup", function() {

    //     var  value = $(this).val().toLowerCase();

    //     if(value.length > 3){

    //         setTimeout(send_ajax(value), 1000);

    //     }


    //     function send_ajax(value){

    //             $.ajax({
    //                 url: '/bitrix/services/main/ajax.php?',
    //                 type: 'GET',
    //                 data: { 
    //                     c: 'opensource:order',
    //                     action: 'searchLocation',
    //                     mode: 'ajax',
    //                     q: value,
    //                 },
    //                 error: function (error) {
    //                     // callback();
    //                     console.log(error)
    //                 },
    //                 success: function (res) {
    //                     if (res.status === 'success') {
    //                         // callback(res.data);
    //                         // console.log(res)

    //                         // $('.location-search').innerHTML='';

    //                         var a = document.getElementById('location-search-select');
    //                         a.options.length = 0;

    //                         var location_search = $('.location-search');

    //                         for (var i = res.data.length - 1; i >= 0; i--) {

    //                             let new_option =  new Option(res.data[i].label, res.data[i].code);

    //                             location_search.append(new_option);
    //                         }


    //                     } else {
    //                         console.log(res);
    //                         // callback();
    //                     }
    //                 }
    //             });
                

    //     }


    // });

    $("#location-search-select").on('selected', function() {

        console.log($(this));

    });

    let select = $('.location-search').selectize({
        valueField: 'code',
        labelField: 'label',
        searchField: 'label',
        create: false,
        render: {
            option: function (item, escape) {
                return '<div class="title">' + escape(item.label) + '</div>';
            }
        },
        load: function (q, callback) {
            if (!q.length) return callback();

            var query = {
                c: 'opensource:order',
                action: 'searchLocation',
                mode: 'ajax',
                q: q
            };

            $.ajax({
                url: '/bitrix/services/main/ajax.php?' + $.param(query, true),
                type: 'GET',
                error: function () {
                    callback();
                },
                success: function (res) {
                    if (res.status === 'success') {
                        callback(res.data);
                        console.log(res)
                    } else {
                        console.log(res.errors);
                        callback();
                    }
                }
            });
        },
        onChange: function(value){

            var query = {
                c: 'opensource:order',
                action: 'calculateDeliveries',
                mode: 'ajax',
            };

             var data = $('#os-order-form').serialize();

             
            var request = $.ajax({
                url: '/bitrix/services/main/ajax.php?' + $.param(query),
                method: 'POST',
                data: data
            });
             
            request.done(function (response) {


                // console.log(response);
                 $('#delivery').empty()
                 // $('#delivery').remove('div')

                for (let key in response.data) {
                    console.log(key)
                    console.log( response.data[key])
                    let item = $('<label><input type="radio" name="delivery_id" value="' + key + '">'+ response.data[key].name +',' + response.data[key].price + ' ₽, '+ response.data[key].period +' </label><br>');
                    // console.log( item)
                    $('#delivery').append(item);
                }

                // let keys = [];

                //  for (let key in response) {      
                //      if (response.hasOwnProperty(key)) keys.push(key);
                //  }

                //  console.log(keys)


                // for (let i=300; i < keys.length && i < 600; i++) { 
                //    console.log(keys[i], yourobject[keys[i]]);
                // }

                // for (var i = response.data.length - 1; i >= 0; i--) {

                //     console.log(response)
                //     console.log($response.data[i])

                //     console.log($item)

                //     $('#delivery').append(item);
                // }

            });










        }

    });

    // console.log(select[0]);

    // var selectizeControl = select[0].selectize;

    // selectizeControl.on('change', function() {

    //       var test = selectize.getValue();
    //       alert(test);

    // });


    // selectizeControl.on('change', function() {

    //   var test = selectize.getValue();
    //   alert(test);

    // });



});
