$(document).ready(function() {


	$(document).on('click', '.on_order-btn', function (e) {

		$('#modal-avtocatalog-item .modal__thanks').hide();

        e.preventDefault();

        var title = $(this).siblings('.analog__name').text();
        var kod = $(this).siblings('.anolog__kod ').text();



        var text_change = $('#modal-avtocatalog-item .modal__title').text();


        // FOR INPUTS

        $('#modal-avtocatalog-item .input-product-kod').val(kod);
        $('#modal-avtocatalog-item .input-product-name').val(title);

        // FOR INPUTS

        $('#modal-avtocatalog-item .modal__title').text(title);
        $('#modal-avtocatalog-item').show()
        $('#modal-avtocatalog-item .modal__open').show();

       
    })


    /* modal-applicability*/

    $(document).on('click', '.data-comment', function (e) {

		$('#modal-applicability .modal__thanks').hide();

        e.preventDefault();

        var title = $(this).data('comment')
        var text_change = $('#modal-applicability .modal__title').text();


        $('#modal-applicability .modal__title').html(title);
        $('#modal-applicability').show()
        $('#modal-applicability .modal__open').show();

       
    })

     /* modal-applicability*/


    /* modal-applicability*/

    $(document).on('click', '.data-analogcode', function (e) {

		$('#modal-analogs .modal__thanks').hide();

        e.preventDefault();

        var title = $(this).data('analogcode')
        var text_change = $('#modal-analogs .modal__title').text();


        $('#modal-analogs .modal__title').html(title);
        $('#modal-analogs').show()
        $('#modal-analogs .modal__open').show();

       
    })

     /* modal-applicability*/


})