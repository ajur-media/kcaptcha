# Опции конфигурации

- length - количество символов в капче
- width - ширина картинки в пикселях (160)
- height - высота картинки в пикселях (80)
- fluctuation_amplitude - амплитуда вертикальной флуктуации символов, в пикс. (8)
- white_noise_density - плотность белого шума, 0 - выключено (1/6)
- black_noise_density - плотность черного шума, 0 - выключено (1/30)
- no_spaces - повысить силу капчи, убрав пробелы между символы (true)
- show_credits - показать строчку копирайтов (false)
- credits - текст на строчке копирайтов (по умолчанию - `$_SERVER['HTTP_HOST']`)
- foreground_color - цвета изображения капчи: текст
- background_color - цвета изображения капчи: фон
- jpeg_quality - качество сохраняемого изображения JPEG (90)

Легаси опции, которые не реализованы:

- codeSet - (не используется), видимо, должно влиять на `allowed_symbols`