/*jslint node: true */
"use strict";


$(document).ready(function () {

	$(window).resize(function () {
		WindowResize();
	});

	VinSearch($('#VinSearchForm form'));
	$('#Languages a').click(function () {
		var Lang = $(this).parent().attr('data-language');
		setCookie('language', Lang, {expires: 60 * 60 * 24 * 365, path: '/'});
	});
	$('#Vins div.VinCard div.Options div.Header').click(function () {
		$(this).toggleClass('Opened').parent().toggleClass('Wide').children('ul').toggle('blind', 100);
	});
	$('#Body div.List.Multilist').on('click', 'div.Header', function () {
		$(this).next().toggle('blind', 100);
	});
	PartHighLight($('#myMap div, .analog__inner .analog__item'));
	FormInit();
	MobileInterface();
	PartAdditionalInfoInit();

	FiltersInit();

	ErrorFoundInit();
	CatSetupInit();
	//$('#CatSetupText a').trigger('click');
	//$('#ErrorFoundText a').trigger('click');

	$('.Tiles .image a img[Data-MagnifiedTitle]').each(function () {
		$(this).tooltip({
			//items: "img[alt]"
			//content: "<div class='MagnifiedTitle'><img src='"+$(this).attr('Data-MagnifiedTitle')+"'></div>"
			content: "<div class='MagnifiedTitle' style='background-image: url(" + $(this).attr('Data-MagnifiedTitle') + ")'></div>"
		})
		//	.tooltip('open')
		;
	});

	$('#Body').on('click', '.AdvancedTiles .image a', function () {
		var This = $(this).parent().parent().clone(),
			Title = This.find('.ifHeaderText').detach().text(),
			TilePopup = $("<div/>", {'class': 'AdvancedTilePopup'}).append(This),
			Image = TilePopup.find('a img');
		Image.attr('src', Image.attr('Data-imageBig'));

		TilePopup.find('.image').detach().prependTo(TilePopup).find('img').unwrap().attr('alt', '');
		TilePopup.find('.number').detach().appendTo(TilePopup.find('.image'));

		$('#Dialog').html(TilePopup).dialog({
			width: Math.min($(window).width() - 40, 600),
			modal: true,
			title: Title,
			position: {my: "center center", at: "center center", of: window}
		});
		return false;
	});


	/*
	var Scroll = {DIV: {Element: 'html', ToElement: 'tr.TR-'}, TR: {Element: 'div.ImageArea', ToElement: '#myMap div.Reg-'}};
			$(Scroll[$(this).prop('tagName')]['Element']).scrollTo($(Scroll[$(this).prop('tagName')]['ToElement'] + $(this).attr('data-ID')), 100, {offset: -40});
	* */
	Chinatown();

	function Chinatown() {
		if (location.href.indexOf('chinatown74.ru') != -1) {
			var Links = $('[href*="www.neoriginal.ru/spares"]');
			Links.each(function () {
				$(this).attr('href', $(this).attr('href').replace(/\/\/www\.neoriginal\.ru\/spares\/(.+)\//g, '//chinatown74.ru/shop/search/?query='));
			});
		}
	}

});
window.onload = function () {
	WindowResize();

	ImageScale();
	FixImagePosition();
	CenterPartImage();
	if (location.hash != '') {
		$('tr.TR-' + location.hash.substr(1)).trigger('click');
		$('#myMap div.Reg-' + location.hash.substr(1)).trigger('click');

	}

	var Catalog = $('#MainMenu li:eq(1)').text();
	if (Catalog === 'Каталог: Ssangyong' ||
		Catalog === 'Каталог: Bmw'
	) {
		$('.partAdditionalInfo').parent('td').wrapInner('<div class="FlexCell">');
		$('.ifImage .Info table tr').each(function () {
			var FlexCell = $(this).find('.FlexCell');
			//alert($(this).height());
			FlexCell.height($(this).height() - 10);
		});
	}
};


function ErrorFoundInit() {
	$('#ErrorFoundText a').click(function () {
		//$('#ErrorFoundDialog .MessageSend').hide();
		//$('#ErrorFoundDialog textarea').show();
		//$('#ErrorFoundDialog input').show();
		$('#ErrorFoundDialog').dialog({
			width: 400,
			title: 'Сообщение об ошибке',
			modal:true,
			buttons: {
				'Отмена': function () {
					$(this).dialog('close');
				},
				'Отправить': function () {
					if ($(this).find('textarea').val() == '') {
						$(this).find('textarea').focus();
						HighliteError($(this).find('textarea'));
					}/* else
						if (!$('#recaptcha-anchor').hasClass('recaptcha-checkbox-checked')) {
							let CaptchaIframeDom=$('#ErrorFoundDialog').find('iframe').contents();
							let Captcha=CaptchaIframeDom.find('.rc-anchor-center-container');
							show(CaptchaIframeDom);
							HighliteError($('iframe .recaptcha-checkbox-border'));
						}*/

						else AJAXErrorFoundSend($(this).find('form'));
				}
			}
		});
		return false;

	});

	//$('#ErrorFoundText a').trigger('click');
}

function CatSetupInit() {
	let catSetupLink=$('#CatSetupText a');
	catSetupLink.click(function () {
		$('#CatSetupDialog').dialog({
			width: 400,
			title: 'Заявка на подключение каталогов',
			modal:true,
			buttons: {
				'Отмена': function () {
					$(this).dialog('close');
				},
				'Отправить': function () {
					let AuthorInput = $(this).find('input[name=Author]'),
						EmailInput = $(this).find('input[name=Email]'),
						SkypeInput = $(this).find('input[name=Skype]'),
						PhoneInput = $(this).find('input[name=Phone]'),
						DomainInput = $(this).find('input[name=Domain]'),
						MessageInput = $(this).find('textarea[name=Message]'),
						CyrillicRegExp=new RegExp(/[а-яА-Я]/),
						DomainRegExp=new RegExp(/.+\.\w+/),
						error = false;
					if (AuthorInput.val() == '') {
						AuthorInput.focus();
						HighliteError(AuthorInput);
						error = true;
					} else {
						if (EmailInput.val() == '') {
							EmailInput.focus();
							HighliteError(EmailInput);
							error = true;
						} else {
							if (EmailInput.val() == '') {
								EmailInput.focus();
								HighliteError(EmailInput);
								error = true;
							} else {
								if (DomainInput.val() == '' || !DomainRegExp.test(DomainInput.val())) {
									DomainInput.focus();
									HighliteError(DomainInput);
									error = true;
								} else {
									if (MessageInput.val() !== '' && !CyrillicRegExp.test(MessageInput.val())) {
										MessageInput.focus();
										alert('We accept messages only in Russian.\n' +
											'Use online translation services.');
										HighliteError(MessageInput);
										error = true;
									}
								}
							}
						}
					}
					if (!error) {
						//alert('Ok');
						AJAXCatSetupSend($(this).find('form'));
					}
					// if ($(this).find('textarea').val() == '') {
					// 	$(this).find('textarea').focus();
					// 	HighliteError($(this).find('textarea'));
					// } else 
				}
			}
		});
		return false;

	});

	if (catSetupLink.attr('data-autoOpen')=='1') catSetupLink.trigger('click');
}

function AJAXErrorFoundSend(Form) {
	FormData = Form.serialize();

	Form.find('textarea').addClass('Animated');
	//alert(FormData);
	$.ajax({
		type: 'POST',
		url: '/',
		data: FormData,
		success: function (Answer) {
			Form.find('textarea').removeClass('Animated').hide();
			Form.find('input').hide();
			Form.find('.g-recaptcha').hide();
			$('#ErrorFoundDialog .MessageSend').html(Answer).show();
			setTimeout(function () {
				$('#ErrorFoundDialog').dialog('close');
			}, 2000);
		},
		error: function (xhr, str) {
			alert('Возникла ошибка: ' + xhr.responseText);
		},
		dataType: 'json'
	});
}
function AJAXCatSetupSend(Form) {
	FormData = Form.serialize();

	Form.find('textarea').addClass('Animated');
	//alert(FormData);
	$.ajax({
		type: 'POST',
		url: '/',
		data: FormData,
		success: function (Answer) {
			Form.find('textarea').removeClass('Animated').hide();
			Form.find('input').hide();
			Form.find('.g-recaptcha').hide();
			$('#CatSetupDialog .MessageSend').html(Answer).show();
			setTimeout(function () {
				$('#CatSetupDialog').dialog('close');
			}, 2000);
		},
		error: function (xhr, str) {
			alert('Возникла ошибка: ' + xhr.responseText);
		},
		dataType: 'json'
	});
}

function HighliteError(El) {

	El.addClass('Highlited');
	var Delay = 300;
	setTimeout(function () {
		El.toggleClass('Highlited');
		setTimeout(function () {
			El.toggleClass('Highlited');
			setTimeout(function () {
				El.toggleClass('Highlited');
				setTimeout(function () {
					El.toggleClass('Highlited');
					setTimeout(function () {
						El.toggleClass('Highlited')
					}, Delay);
				}, Delay);
			}, Delay);
		}, Delay);
	}, Delay);
}

function FiltersInit() {
	if (!$('.ifAdvancedForm').length) return;

	function ValToSlider(V, MinV, MinP, Scale) {
		return (Math.log(V) - MinV) / Scale + MinP;
	}


	$('.ifAdvancedForm .Range').each(function () {
		var Slider = $(this).find('.Slider'),
			Low = $(this).find('.Min'),
			High = $(this).find('.Max'),
			LowV = Low.val().replace(',', '.') * 1,
			HighV = High.val().replace(',', '.') * 1,
			Min = Slider.attr('Data-Min').replace(',', '.') * 1,
			Max = Slider.attr('Data-Max').replace(',', '.') * 1,
			SliderTypeRatio = 1000,
			SliderType = 'Linear';

		if (Max / Min > SliderTypeRatio) SliderType = 'Logarithmic';
		Slider.attr('Data-SliderType', SliderType);

		if (SliderType === 'Linear') {
//alert(Min);
			Slider.slider({
				range: true,
				min: Min,
				max: Max,
				values: [LowV, HighV],
				slide: function (event, ui) {
					Low.val(ui.values[0]);
					High.val(ui.values[1]);
				},
				change: function (event, ui) {
					FilterLink($(this));
				}
			});

			Low.change(function () {
				if (Low.val() * 1 > High.val() * 1) Low.val(High.val());
				Slider.slider("option", "values", [Low.val() * 1, High.val() * 1]);
			});

			High.change(function () {
				if (High.val() * 1 < Low.val() * 1) High.val(Low.val());
				Slider.slider("option", "values", [Low.val() * 1, High.val() * 1]);
			});
		} else {
			//alert(MinV);
			var MinP = 0,
				MaxP = 100,
				MinV = Math.log(Min),
				MaxV = Math.log(Max),
				Scale = (MaxV - MinV) / (MaxP - MinP),
				LowV = (Math.log(LowV) - MinV) / Scale + MinP,
				HighV = (Math.log(HighV) - MinV) / Scale + MinP,
				Precision = 0;
			if (MinV < 1) Precision = 1;

			Slider.slider({
				range: true,
				min: 0,
				max: 100,
				values: [LowV, HighV],
				slide: function (event, ui) {

					Low.val(Math.exp(MinV + Scale * (ui.values[0] - MinP)).toFixed(Precision));
					High.val(Math.exp(MinV + Scale * (ui.values[1] - MinP)).toFixed(Precision));
				},
				change: function (event, ui) {
					FilterLink($(this));
				}
			});

			Low.change(function () {
				if (Low.val() * 1 > High.val() * 1) Low.val(High.val());
				Slider.slider("option", "values", [ValToSlider(Low.val() * 1, MinV, MinP, Scale), ValToSlider(High.val() * 1, MinV, MinP, Scale)]);
			});

			High.change(function () {
				if (High.val() * 1 < Low.val() * 1) High.val(Low.val());
				Slider.slider("option", "values", [ValToSlider(Low.val() * 1, MinV, MinP, Scale), ValToSlider(High.val() * 1, MinV, MinP, Scale)]);
			});

		}


	});

	$('.ifAdvancedForm .FilterHeader').click(function () {
		$('.ifAdvancedForm .Filters').toggle();
	});

	$('.ifAdvancedForm .PopFieldsButtons span').click(function () {
		$(this).addClass('Active').siblings().removeClass('Active');
		if ($('.ifAdvancedForm .PopFieldsButtons span.NotPopular').hasClass('Active')) $('.ifAdvancedForm .Filters .PopGroup.NotPopular').show(); else $('.ifAdvancedForm .Filters .PopGroup.NotPopular').hide();
	});
	$('.ifAdvancedForm .FieldHeader').click(function () {
		$(this).parent().toggleClass('Active');
	});

	$('.ifAdvancedForm .FieldHeader .PopGroupButtons span').click(function (event) {
		$(this).addClass('Active').siblings().removeClass('Active');
		if ($('.ifAdvancedForm .FieldHeader .PopGroupButtons span.NotPopular').hasClass('Active')) $(this).parent().parent().parent().addClass('ShowAll'); else $(this).parent().parent().parent().removeClass('ShowAll');
		$(this).parent().toggleClass('Active');
		event.stopPropagation();
	})

	$('.ifAdvancedForm input[type=checkbox]').change(function () {
		FilterLink($(this));
	});


	function FilterLink(This) {
		var Link = {};
		$('.ifAdvancedForm .AdvancedFormField').each(function () {

			if ($(this).attr('Data-FieldType') === 'range') {
				var Slider = $(this).find('.Slider'),
					MinV = $(this).find('.Min').val(),
					MaxV = $(this).find('.Max').val(),
					Val = {},
					Values = Slider.slider("option", "values");
				if ((Slider.attr('Data-SliderType') === 'Logarithmic' && (Values[0] != 0 || Values[1] != 100)) ||
					(Slider.attr('Data-SliderType') === 'Linear' && (Values[0] != Slider.slider("option", "min") || Values[1] != Slider.slider("option", "max")))) {
					Val.min = Slider.attr("Data-MinV");
					Val.max = Slider.attr("Data-MaxV");
					Val.selectedLow = MinV;
					Val.selectedHigh = MaxV;
				}

			} else {
				var Val = [];
				$(this).find('input:checked').each(function () {
					Val.push($(this).val());
				});
			}

			if (Val.length || !$.isEmptyObject(Val)) Link[$(this).attr('Data-FieldId')] = Val;
		});
		var Uri;
		Uri = Link = JSON.stringify(Link);
		if ($('.ifAdvancedForm').attr('Data-fpEncodeMethod') === 'base64') Link = window.btoa(Link);

		var ApiLink = "<a href='http://apibackend.ilcats.ru/?brand=oils&apiVersion=2.0&debug=36347a&q&a&r&a&q&a&aa&aaaa&function=getParts&type=1&oi12234&&filterData=" + Link + "' target='_blank'>apibackend</a>";

		Link = $('.ifAdvancedForm').attr('Data-AjaxLink') + '&' + $('.ifAdvancedForm').attr('Data-fpFormDataUrlParamName') + '=' + Link;


		//$('#Dialog').html(Uri + '<br><br>' + Link + '<br><br>' + ApiLink).dialog({width: 600, position: {my: "right top", at: "right top", of: window}});

		AJAXSend(Link);
	}

	$('.ifAdvancedForm input:checked').parents('.AdvancedFormField').addClass('Active');

	$('.ifAdvancedForm .Range').each(function () {
		if ($(this).find('.Slider').attr('Data-Min') !== $(this).find('.Min').val() ||
			$(this).find('.Slider').attr('Data-Max') !== $(this).find('.Max').val()) {
			$('.ifAdvancedForm .Range').parents('.AdvancedFormField').addClass('Active');
		}

	});

	$('.ifAdvancedForm .AdvancedFormField.Active').parent().show().parent().show();
	$('.ifAdvancedForm .PopGroup:visible').parent().show();

	$('.PopGroup.NotPopular:visible').siblings('.PopFieldsButtons').find('span').removeClass('Active').filter('.NotPopular').addClass('Active');

	//$('.ifAdvancedForm .PopFieldsButtons span').trigger('click');
	//$('.ifAdvancedForm .PopGroup.NotPopular .AdvancedFormField:lt(4) .FieldHeader').trigger('click');
	//$('.ifAdvancedForm .AdvancedFormField input:eq(1)').trigger('click');
}


function AJAXSend(Link) {
	//FormData=Form.serialize();

	$('#Body .Tiles').addClass('Animated');
	$.ajax({
		type: 'GET',
		url: Link,
		//data: FormData,
		success: function (Answer) {
			//$('#Body .PageSelector').replaceWith(Answer['PageSelector']).removeClass('Animated');
			$('#Body .PageSelector').remove();
			$('.Tiles.AdvancedTiles').before(Answer['PageSelector']);
			$('#Body .Tiles').replaceWith(Answer['Tiles']).removeClass('Animated');
		},
		error: function (xhr, str) {
			alert('Возникла ошибка: ' + xhr.responseText);
		},
		dataType: 'json'
	});
}


function PartAdditionalInfoInit() {
	var Title = '',
		CloseText = $('.ifImage .Info table').attr('Data-close'), AjaxSelector = '';
	$('.partAdditionalInfo a').click(function () {
		$('.ifImage div.Images div.ImageArea').append('<div class="AnimatedWaiting">');
		Title = $('.ifImage .Info table').attr('Data-additionalInfo') + ' ' + $(this).parent().parent().parent().find('td:eq(1) a:eq(0)').text() + ' ' + $('.ifImage .Info table').attr('Data-brand');
		var ActiveLink = $(this).attr('href');
		$('#Dialog').html('<div class="PartAdditionalInfoLinks"></div><div class="PartAdditionalInfoBody"></div>');
		$(this).parent().children('a').clone().appendTo('#Dialog .PartAdditionalInfoLinks');
		$('#Dialog .PartAdditionalInfoLinks a').each(function () {
			$(this).append(' <span>' + $(this).attr('title') + '</span>');
			if ($(this).attr('href') === ActiveLink) {
				$(this).addClass('Active');
			}
		});
		$('#Dialog .PartAdditionalInfoLinks a').click(function () {
			if (!$(this).hasClass('Active')) {
				$(this).parent().children().removeClass('Active');
				$(this).addClass('Active');
				if ($(this).attr('href').indexOf('getPartImages') === -1) {
					AjaxSelector = ' table[data-additionalinfo]';
				} else {
					AjaxSelector = ' div.ifImage div.Images div.ImageArea div.Image';
				}
				$('#Dialog div.PartAdditionalInfoBody').html('').load(
					$(this).attr('href') + AjaxSelector,
					function () {
						DialogPos();
					}
				);
			}

			return false;
		});
		if ($(this).attr('href').indexOf('getPartImages') === -1) {
			AjaxSelector = ' table[data-additionalinfo]';
		} else {
			AjaxSelector = ' div.ifImage div.Images div.ImageArea div.Image';
		}
		$('#Dialog div.PartAdditionalInfoBody').html('').load(
			$(this).attr('href') + AjaxSelector,
			function () {
				$('.ifImage div.Images div.ImageArea .AnimatedWaiting').remove();

				$('#Dialog').dialog({
					width: Math.min(600, $(window).width() * 0.8),
					maxHeight: $(window).height() * 0.8,
					modal: true,
					title: Title,
					closeText: CloseText
				});
				DialogPos();
			});

		return false;

	});

	function DialogPos() {
		$('#Dialog').parent().position({my: "center center", at: "center center", of: window});
	}
}


function Columns() {
	var ColWidth = 400,
		List = $('#Body>div.List');
	List.find("div.Column").each(function () {
		$(this).replaceWith($(this).html());
	});
	$('#Body>div.List').each(function () {
		var Blocks = $(this).find('>div').length,
			Columns = Math.floor($(this).width() / ColWidth);
		if (!Columns) Columns = 1;
		for (var i = 0; i < Columns; i++) {
			$(this).find('>div:not(.Column):lt(' + Math.ceil(Blocks / Columns) + ')').wrapAll("<div class='Column' style='width:" + Math.floor(($(this).width() - (Columns - 1) * 30) / Columns) + "px'></div>");
		}
	});
}

function FormInit() {
	$('#Body .Form button').click(function () {
		var Form = $(this).parent().parent('.Form'),
			Inputs = Form.find('input:checked, select'),
			Params = '';
		Inputs.each(function () {
			if ((Form.attr('data-fpFormDataUnknownValue') && $(this).val() != Form.attr('data-fpFormDataUnknownValue')) || (!Form.attr('data-fpFormDataUnknownValue') && $(this).val())) {
				if (Params) Params = Params + Form.attr('data-FieldsDelimeter');
				Params = Params + $(this).prop('name') + Form.attr('data-ValuesDelimeter') + $(this).val();
			}
		});
		switch (Form.attr('data-EncodeMethod')) {
			case 'base64':
				Params = window.btoa(Params);
				break;
		}
		location.href = Form.attr('data-URL') + Params;
		return false;
	});
}

function IfImagePageResize() {
	var IfImageH;
	if ($('.ifImage').length) {
		IfImageH = $(window).height() - $('.ifImage').offset().top - 120;
		//IfImageH = documentElement.clientWidth/Height;
	}
//alert ($('#Body').width());
	if ($('#Body').width() <= 980) $('#Body div.ifImage').height('auto');// else $('#Body div.ifImage').height(IfImageH);
	//$('#Body div.ifImage>*').height(IfImageH);
	$('#Body div.Images div.ImageArea').height($(window).height() - 100);

	VINOptionResize();
}

function CenterPartImage() {
	$('.ifImage .Images .Move button').click(function () {
		$('.ifImage .Images .Image').position({
			my: 'center top',
			at: 'center top',
			of: $('.ifImage .Images .ImageArea')
		});
	});
	$('.ifImage .Images .Move a').click(function () {
		$('.ifImage .Images .Move button').trigger('click');
		return false;
	});
}

function ImageScale() {
	var Image = $('#Body .ifImage div.Images img'),
		Map = $('#Body .ifImage div.Images map'),
		ImageWrap = $('#Body .ifImage div.Images div.Image'),
		ImageArea = $('#Body .ifImage div.Images div.ImageArea');
	ImageArea.css('height', ($(window).height() - 100) + 'px');
	ImageResize(0);
	ImageWrap.pep({
		elementsWithInteraction: 'div',
		startPos: {left: (ImageArea.width() - ImageWrap.width()) / 2, top: 0},
		//constrainTo: 'parent'
	});


	$("#ImagesControlPanel button.ScaleStep").unbind('click').click(function () {
		ImageResize($(this).attr('data-Direction'));
	});

	function ImageResize(Direction) {
		function NC(Coord) {
			return parseInt(Coord) / 100 * NewScale + 'px';
		}

		Image.css('width', '1px').css('height', '1px');
		var ImageAreaWidth = ImageArea.width();
		Image.removeAttr('max-width').css('max-width', '').removeAttr('width').css('width', '').removeAttr('max-height').css('max-height', '').removeAttr('height').css('height', '');

		ImageArea.css('ovetflow', 'hidden');
		//if (Direction===0) {var NewScale=Math.floor(Math.min(ImageAreaWidth/Image.width(), ImageArea.height()/Image.height())*10)*10; if (NewScale<20) NewScale=20;}
		if (Direction === 0) {
			var NewScale = Math.floor(Math.min(ImageAreaWidth / Image.width()) * 10) * 10 - 0;
			if (NewScale > 40) {
				var ImageAreaHeight = ImageArea.height(),
					NewHeightScale;
				if (ImageArea.height() < Image.height() * NewScale / 100) ;
				NewHeightScale = Math.floor(Math.min(ImageAreaHeight / Image.height()) * 10) * 10 - 0;
				if (NewHeightScale < NewScale)
					NewScale = NewHeightScale;
				//alert(NewHeightScale);
			}
			if (NewScale < 20) NewScale = 20;


		} else {
			var NewScale = parseInt($("#ImagesControlPanel button.CurrentScale").text()) + 10 * Direction;
		}

		//alert(ImageArea.width());
		var NewW = Math.floor(Image.width() * NewScale / 100), NewH = Math.floor(Image.height() * NewScale / 100);
		Image.css('width', NewW).css('height', NewH);
		ImageWrap.css('width', NewW).css('height', NewH);
		$('#myMap div').each(function () {
			var Coords = JSON.parse($(this).attr('data-Coords'));
			$(this).height(NC(Coords[3])).width(NC(Coords[2])).css('left', NC(Coords[0])).css('top', NC(Coords[1]));
		});
		$("#ImagesControlPanel button.CurrentScale").html(NewScale + '%');
		if (!Image.width()) $("#ImagesControlPanel").hide();
		if (NewScale <= 20) {
			$("#ImagesControlPanel button.First").attr('disabled', true);
		} else {
			$("#ImagesControlPanel button.First").attr('disabled', false);
		}
		if (NewScale >= 200) {
			$("#ImagesControlPanel button.Last").attr('disabled', true);
		} else {
			$("#ImagesControlPanel button.Last").attr('disabled', false);
		}
	}

	//CenterPartImage();
}

function FixImagePosition() {
	if ($('#fixed').length) {
		var offset = $('#fixed').offset();
		var topPadding = 50, bottomPadding = 50;
		var PageHeight = document.body.scrollHeight;
		var NewOffset, MaxOffset;
		$(window).scroll(function () {
			var ScrollTop = $(window).scrollTop(),
				ImagesOffsetTop = $('.ifImage').offset().top;
			/*if (ScrollTop > offset.top && ScrollTop<PageHeight-$(window).height()-bottomPadding) {
				$('#fixed').stop().animate({marginTop: ScrollTop-ImagesOffsetTop+topPadding}, 'fast');
			}
			else {
				if (ScrollTop < offset.top) $('#fixed').stop().animate({marginTop: 0});
			}*/
			if (ScrollTop > offset.top) {
				NewOffset = ScrollTop - ImagesOffsetTop + topPadding;
				if (NewOffset > (MaxOffset = $('.ifImage').height() - $('.ImageArea').height() - topPadding))
					NewOffset = MaxOffset;
			} else NewOffset = 0;
			$('#fixed').stop().animate({marginTop: NewOffset}, 'fast');
		});
		$(window).trigger('scroll');
	}
};

function MobileInterface() {
	if ($("html").width() < 1000) {

		$('#Languages').click(function (event) {
			$('#MainMenu').removeClass('Opened').find('li:not(.Image)').hide('blind', 100);
			$(this).addClass('Opened').find('li').show('blind', 200);
			event.stopPropagation();
		});
		$('#MainMenu').click(function (event) {
			$('#Languages').removeClass('Opened').find('li:not(.Selected)').hide('blind', 100);
			$(this).addClass('Opened').find('li').show('blind', 200);
			event.stopPropagation();
		});
		$('html').click(function () {
			$('#Languages').removeClass('Opened').find('li:not(.Selected)').hide('blind', 200);
			$('#MainMenu').find('li:not(.Image)').hide('blind', 200).delay(200).parent().removeClass('Opened');
		});
	}
}

function PartHighLight(Regions) {
	Regions.each(function () {
		// $(this).mouseover(function () {
		// 	$('.product__analog .TR-' + $(this).attr('data-ID') + ', #myMap div.Reg-' + $(this).attr('data-ID')).addClass('HighLighted');
		// });
		// $(this).mouseout(function () {
		// 	$('.product__analog .TR-' + $(this).attr('data-ID') + ', #myMap div.Reg-' + $(this).attr('data-ID')).removeClass('HighLighted');
		// });

		$(this).click(function () {
			if ($(this).hasClass('NotUsable')) {
				$('#Dialog').addClass('Alert').html($(this).attr('data-NotUsableAlert')).dialog({
					width: 400,
					modal: true,
					title: $(this).attr('data-NotUsableTitle')
				});
			} else {
				if ($(this).prop('tagName') === 'TR') {
					$('div.ImageMap').css('top', '0').css('left', '0');
				}

				console.log($(this))

				if($(this).hasClass('Opacity')){
					$('.analog__item').removeClass('Highlited')
					console.log(11111111)
					$('.TR-' + $(this).attr('data-ID')).addClass('Highlited')

					var Scroll = {DIV: {Element: 'html', ToElement: 'div.TR-'}, TR: {Element: 'div.ImageArea', ToElement: '#myMap div.Reg-'}};

					$(Scroll[$(this).prop('tagName')]['Element']).scrollTo($(Scroll[$(this).prop('tagName')]['ToElement'] + $(this).attr('data-ID')), 100, {offset: -40});

					var LeftOffseft = Math.floor($('div.ImageArea').width() - $('div.ImageArea img').width()) / 2;

				}else {
					$('.analog__item').removeClass('Highlited')
					$(this).addClass('Highlited')
				}



				$('div.Images div.ImageMap').css('left', parseInt($('div.Images div.ImageMap').css('left')) + LeftOffseft + 'px');
				$('div.Info div, #myMap div').removeClass('Choosen');
				$('div.Info .TR-' + $(this).attr('data-ID') + ', #myMap div.Reg-' + $(this).attr('data-ID')).addClass('Choosen');
			}
		});
	});
}

function setCookie(name, value, options) {
	options = options || {};

	var expires = options.expires;

	if (typeof expires == "number" && expires) {
		var d = new Date();
		d.setTime(d.getTime() + expires * 1000);
		expires = options.expires = d;
	}
	if (expires && expires.toUTCString) {
		options.expires = expires.toUTCString();
	}

	value = encodeURIComponent(value);

	var updatedCookie = name + "=" + value;

	for (var propName in options) {
		updatedCookie += "; " + propName;
		var propValue = options[propName];
		if (propValue !== true) {
			updatedCookie += "=" + propValue;
		}
	}

	document.cookie = updatedCookie;
}

function getCookie(name) {
	var matches = document.cookie.match(new RegExp(
		"(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
	));
	return matches ? decodeURIComponent(matches[1]) : undefined;
}

function WindowResize() {
	Columns();
	IfImagePageResize();
}

function VinSearch(Form) {
	Form.find('button').click(function () {
		var VinInput = Form.find('input');
		if (VinInput.val()) {
			document.location.href = Form.attr('data-Link').replace('vinValue', VinInput.val());
		} else {
			VinInput.attr('placeholder', 'Введите VIN/FRAME').focus();
		}
		return false;
	});
	$('#Vins div.CurrentVin').click(function () {
		$(this).toggleClass('Opened');
		$('#Vins div.VinInfo').toggle('blind', 100);
	});
	$('.VinCard table a').click(function () {
		var UrlAppend = '', Error = false;
		if ($('.VinCard table select').length) {
			$('.VinCard table select').each(function () {
				if ($(this).val()) UrlAppend = UrlAppend + '&' + $(this).attr('data-Name') + '=' + $(this).val();
				else {
					Error = true;
					$(this).fadeOut(200).fadeIn(200).fadeOut(200).fadeIn(200);
				}
			});
			if (!Error) $(this).attr('href', $(this).attr('href') + UrlAppend);
		}
		return !Error;
	});
}

function VINOptionResize() {
	if ($("html").width() < 1000) {
		$('#Vins .VinCard').each(function () {
			$(this).find('td.Center a').before($(this).find('div.Options'));
		});
	}
}




























