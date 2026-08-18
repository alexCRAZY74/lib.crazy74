$(function () {
	// 1. Проверка crazy74.number
	function updateNumbers() {
		const raw = $('#input-number').val();
		const num = Number(raw) || 0;

		const smart = crazy74.number.smartFormat(num, false, false, 0, 2);
		const bytes = crazy74.number.sizebytes(num);
		const ending = crazy74.number.withEnding(num, ['элемент', 'элемента', 'элементов'], 'нет элементов');

		$('#res-smartFormat').text(smart);
		$('#res-sizebytes').text(bytes);
		$('#res-withEnding').html(ending);
	}

	$('#input-number').on('input change', updateNumbers);

	// 2. Проверка crazy74.date и SmartTime
	function updateDates() {
		const raw = $('#input-date').val();
		const parsed = crazy74.date.parseDate(raw);

		if (parsed) {
			$('#res-dateFormatLocale').text(crazy74.date.formatLocale(parsed, { withTime: true }));
			$('#res-dateFmtSmart').text(crazy74.date.fmtSmart(parsed));
			$('#res-dateFmtAgo').text(crazy74.date.fmtAgo(parsed));

			// Инициализация живого таймера на элементе
			$('#res-smarttime').SmartTime(raw);
		} else {
			$('#res-dateFormatLocale').text('Некорректная дата');
			$('#res-dateFmtSmart').text('-');
			$('#res-dateFmtAgo').text('-');
			$('#res-smarttime').text('-');
		}
	}

	$('#input-date').on('input change', updateDates);

	$('.btn-preset').on('click', function () {
		const preset = $(this).data('time');
		let now = new Date();

		if (preset === '-5min') now.setMinutes(now.getMinutes() - 5);
		else if (preset === '-2hour') now.setHours(now.getHours() - 2);
		else if (preset === '-1day') now.setDate(now.getDate() - 1);

		const formatted = crazy74.date.formatLocale(now, { format: 'Y-m-d H:i:s' });
		$('#input-date').val(formatted).trigger('change');
	});

	// 3. Проверка crazy74.bool и crazy74.uuid
	function updateBoolAndUuid() {
		const rawBool = $('#input-bool').val();
		const boolRes = crazy74.bool(rawBool);

		$('#res-boolVal').text(boolRes ? 'true (boolean)' : 'false (boolean)');
		$('#res-uuid-v4').text(crazy74.uuid.get('v4'));
		$('#res-uuid-base36').text(crazy74.uuid.get('base36', 12));
		$('#res-guid-jquery').text($.getGUID());
	}

	$('#input-bool').on('input change', updateBoolAndUuid);
	$('#btn-regen-uuid').on('click', updateBoolAndUuid);

	// 4. Проверка jQuery-плагинов
	$('#demo-form').on('submit input change', function (e) {
		e.preventDefault();
		const formData = $(this).FormData();
		const emptyCheck = $.isEmpty(formData);
		const maxZ = $.maxZIndex();

		$('#res-formData').text(JSON.stringify(formData));
		$('#res-isEmpty').text(emptyCheck ? 'true (пусто)' : 'false (содержит данные)');
		$('#res-maxZIndex').text(maxZ);
	});

	// Первичный прогон
	updateNumbers();
	updateDates();
	updateBoolAndUuid();
	$('#demo-form').trigger('change');
});