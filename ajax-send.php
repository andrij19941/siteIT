<?php

/**
* Очистка данних
*/
function clean($data) {
	return htmlspecialchars(strip_tags(addslashes(trim($data))));
}


/**
 * Очистка телефону
 */
function cleanPhone($value = '')
{
	return str_replace(array('+', ' ', '(' , ')', '-'), '', $value);
}



/**
 * Очистка тексту зі слешами
 */
function viewStr($value = false) {
	return ($value) ? stripslashes($value) : false;
}


/**
 * Добавляємо карточку до TRELLO
 * @param  boolean $value  [ Номер телефону ]
 * @param  [type]  $idList [ Номер списку в який треба добавляти ]
 * @param  [type]  $apiKey [ Api ключ ]
 * @param  [type]  $token  [ Токен ]
 * @return [type]          [ null ]
 */
function trelloAddCard($value = false, $idList, $apiKey, $token, $orderNum)
{
	$name = '&name=';
	$name .= urlencode('Запис учня №'.$orderNum.' - +'.$value);

	$desc = '&desc=';
	$desc .= urlencode('+'.$value);

	$start = '&start=';
	$start .= urlencode(date("Y-m-d H:i:s"));

	$urlAdd = 'https://api.trello.com/1/cards?idList='.$idList.'&key='.$apiKey.'&token='.$token.$name.$desc.$start;

	$ch = curl_init($urlAdd);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$html = curl_exec($ch);
	curl_close($ch);  

	// echo '<pre>'; print_r(json_decode($html, true)); echo '</pre>';
}



/**
 * Перевіряємо чи є телефон серед доданих
 * @param  boolean $value  [ Номер телефону ]
 * @param  [type]  $apiKey [ Api ключ ]
 * @param  [type]  $token  [ Токен ]
 * @return [type]          [ boolean ]
 */
function trelloCheckExist($value = false, $apiKey, $token)
{
	$urlList = 'https://api.trello.com/1/boards/2C9mTaPY/cards?key='.$apiKey.'&token='.$token;

	$ch = curl_init($urlList);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$html = curl_exec($ch);
	curl_close($ch);   

	// Заглушка, яка вказує, що не існує
	$exist['toggle'] = false;
	$exist['num']    = 0;
			
	// Розкодовуємо відповідь
	$html = json_decode($html, true);

	// Перевіряємо чи існує такий запис
	if (is_array($html) and !empty($html)) {
		foreach ($html as $val) {

			$exist['num']++;
			
			if ($val['name'] == $value)
				$exist['toggle'] = true;
		}
	}

	return $exist;
}


/**
 * Відправка листів
 * @param  boolean $sendEmail      [ Email куди відправляти лист ]
 * @param  string  $emailSignature [ Від кого ]
 * @param  boolean $name           [ Ім'я ]
 * @param  boolean $subject        [ Тема ]
 * @param  boolean $message        [ Текст повідомлення ]
 */
function sendEmail (
		$sendEmail = false,
		$subject = false,
		$name = 'Запис учня',
		$emailSignature = 'noreply@gmail.com',
		$message = false) {

	// Додаткові заголовки
	$headers .= 'MIME-Version: 1.0\r\n'.'Content-type: text/html; charset=utf-8\r\n';
	$headers .= ($emailSignature) ? 'From: '. viewStr($name) . ' <'. $emailSignature .'>\r\n' : 'From: '. viewStr($name) .'\r\n';
	$headers .= 'X-Mailer: PHP/' . phpversion();

	// Відправка форми
	return mail($sendEmail, $subject, viewStr($message), $headers);
}


/**
 * Перевіряємо телефон на правильність
 */
function validatePhone($phone = false)
{
	if (!$phone)
		return false;

	return (iconv_strlen($phone) >= 12)
		? false
		: 'Телефон не правильний';
}




// Налаштування для Trello
$apiKey = 'fdfec0424795adc25becaeb1c0c46052';
$token  = '8c0d297c7a5cc105d8c7e9489bc21d201d1f600355f155ac73e278a621bf3de6';
$idList = '62d1b8794075d228eac5240a';

// Поле форми
$phone = (clean($_POST['phone'])) ? cleanPhone(clean($_POST['phone'])) : '';

// Перевіряємо телефон на правильність
$return['error'] = validatePhone($phone);

// Кому будемо відправляти?
$sendEmail = 'hukvadim@gmail.com';
$subject   = 'Учень на курс Frontend';


// Відпрацьовуємо код тільки коли є телефон
if ($phone and !$return['error']) {
	
	// Підраховуємо кількість записів
	$orderNum = 0;

	// Добавляємо, якщо немає
	$trelloCheck = trelloCheckExist($phone, $apiKey, $token);

	// Добавляємо номер, якщо немає
	if (!$trelloCheck['toggle'])
		trelloAddCard($phone, $idList, $apiKey, $token, $trelloCheck['num']);

	// Формуємо листа / email шаблон
	$message = 'Запис учня №'.$trelloCheck['num'].'. Телефон: <a href="tel:+'.$phone.'">+'.$phone.'</a>';

	// Відправляємо листа
	sendEmail($sendEmail, $subject . ' #'.$trelloCheck['num'], false, false, $message);

	// Формуємо дані для повернення в js
	$return['type']  = 'success';
	$return['link'] = 'thank-you.html';
	$return['error'] = 'Успішно відправлено!';
} else {

	// Формуємо дані для повернення в js
	$return['type'] = 'danger';
	$return['error'] = ($return['error']) ? $return['error'] : 'Форма не відправлена';
}


// Кодуємо відповідь у json
echo json_encode($return);