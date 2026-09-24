crazy74.onPageReady(function () {
	var $popper = $('#demo-popper');

	// Кнопка 1: Мульти-объектное позиционирование (X по Target A, Y по Target B)
	$('#btn-align-split').on('click', function () {
		$popper.csl74Position([
			{
				of: '#target-x',
				my: 'left',
				at: 'left bottom'
			},
			{
				of: '#target-y',
				my: 'top right-4',
				at: 'left top'
			}
		]);
	});

	// Кнопка 2: Одиночное позиционирование по Target A
	$('#btn-align-single').on('click', function () {
		$popper.csl74Position({
			of: '#target-x',
			my: 'left top+4',
			at: 'left bottom'
		});
	});
	$popper.csl74Position([
		{
			of: '#target-x',
			my: 'left',
			at: 'left bottom'
		},
		{
			of: '#target-y',
			my: 'top',
			at: 'left top'
		}
	]);
	
	$('#demo-modal').on('click', function () {
		$.csl74Window({
			title: 'Модальное окно',
			content: 'Содержимое окна',
			destroyOnClose: true,
			withBackDrop: false,
			position:[
				[
					{of:'#demo-modal', my: 'left', at: 'left'},
					{of:'#demo-modal', my: 'bottom-6', at: 'top'}
				],
				{collision: 'flipfit'}
			]
		});
	});
	
	$('#demo-menu').csl74Menu({
		customClass: 'demo-menu',
		items: [
			{value: 'view', label: 'Просмотреть', icon: 'csl74-icon-eye'},
			'-',
			'https://alex-crazy74.hashnode.dev/why-even-advanced-ai-breaks-stock-registers-stock',
			{value: 'url1', label: 'https://alex-crazy74.hashnode.dev/why-even-advanced-ai-breaks-stock-registers', icon: 'csl74-icon-link'},
			{value: 'file://E:/Work/e_Trade/_nb_project/r_remainder/article_ru.md', icon: 'csl74-icon-file'},
			'-',
			{value: 'delete', label: 'Удалить', icon: 'csl74-icon-trash', danger: true}
		],
		onSelect: function (value, item, $trigger) {
			console.log('Выбран пункт:', value, item);
		}
	});
	
	// Сама вытащит options, сделает обертку и повесит csl74Menu
	$('#demo-select-1').csl74Select();
	
	$('#demo-select-2').csl74Select({
    items: [
        { value: '1', label: 'Первый пункт', icon: 'csl74-icon-file' },
        { value: '2', label: 'Второй пункт', icon: 'csl74-icon-link' },
        '-',
        { value: '3', label: 'Удалить', icon: 'csl74-icon-trash', danger: true }
    ],
    onSelect: function(val, item) {
        console.log('Выбрано:', val, item);
    }
	});
	
});