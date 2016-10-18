<?php
/**
 * Класс для работы с api вконтакта.
 */
class model_vk {

    private $access_token;
    private $url = "https://api.vk.com/method/";
    private $pause = 400000;  //0.4 сек

    /**
     * Конструктор
     * @param str $access_token - токен для работы с апи вконтакта.
     */
    public function __construct($access_token) {

        $this->access_token = $access_token;
    }

    /**
     * Делает запрос к Api VK
     * @param str $method - вызываемый метод api.
     * @param arr $params - ассоциативный массив параметров для запроса (по-умолчанию: нет).
     * @return mixed ответ от вконтакта в формате json. В случае ошибки - false.
     */
    public function method($method, $params = null) {

        $p = "";
        if( $params && is_array($params) ) {
            foreach($params as $key => $param) {
                $p .= ($p == "" ? "" : "&") . $key . "=" . urlencode($param);
            }
        }
        $response = file_get_contents($this->url . $method . "?" . ($p ? $p . "&" : "") . "access_token=" . $this->access_token);

        if( $response ) {
            return json_decode($response);
        }
        return false;
    }
		
		/**
		 * Загрузить фотографию на серверв вконтакта.
		 * @param str $url  - урл сервера, на который загружать.
		 * @param str $file - адрес фотографии.
		 * @param bool $add_cwd - true: добавить к пути фотографии текущий рабочий каталог (по-умолчанию: false).
		 * @return str ответ от вконтакта в формате json.
		 */
		public function upload_photo ($url, $file, $add_cwd=false) {
			if ($add_cwd)
				$file = getcwd().'/'.$file;
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('photo' => '@'.$file));
			$res = curl_exec($ch);
			curl_close($ch);
			
			if ($res)
				$res = json_decode($res);
			
			return $res;
		}
		
		/**
		 * Загрузить фотографию на стену пользователя/группы.
		 * @param str $type - gid (группа) | uid (пользователь).
		 * @param str $id   - id пользователя/группы.
		 * @param str $file - адрес фотографии.
		 * @param bool $add_cwd - true: добавить к пути фотографии текущий рабочий каталог (по-умолчанию: false).
		 * @return str ответ от вконтакта в формате json.
		 */
		public function save_photo_to_wall ($type, $id, $file, $add_cwd=false) {
			$type = $type == "gid" ? "gid" : "uid";
			if (substr($id, 0, 1) == "-")
				$id = substr($id, 1);
			
			$params = array($type => $id);
			$res = $this->method("photos.getWallUploadServer", $params);
			if ($res)
				$res = $this->upload_photo($res->response->upload_url, $file, $add_cwd);
			if ($res) {
				$params = array(
					"server" => $res->server,
					"photo"  => $res->photo,
					"hash"   => $res->hash,
					$type    => $id,
				);
				$res = $this->method("photos.saveWallPhoto", $params);
			}
			
			return $res;
		}
		
		/**
		 * Разместить запись на стене пользователя/группы.
		 * @param int $owner_id   - id пользователя/группы.
		 * @param str $message    - текст записи.
		 * @param arr $images     - массив с адресами фотографий.
		 * @param int $time       - время публикации записи.
		 * @param int $from_group - 1: опубликовать от имени группы, 0: от своего.
		 *                          Только для группы (по-умолчанию: 1).
		 * @param arr $del_links  - удалять ссылки из текста:
		 *                          2 - оставить только внутренние ссылки вконтакта,
		 *                          1 - обрезать все ссылки,
		 *                          0 - оставить все ссылки (по-умолчанию).
		 * @return str ответ от вконтакта в формате json.
		 */
		public function wall_post ($owner_id, $message, $images=null, $time=0, $from_group=1, $del_links=0) {
			if (!$owner_id)
				return false;
		
			//загружаем картинки
			$attach = array();
			foreach ($images as $img) {
				$img = $this->save_photo_to_wall(substr($owner_id, 0, 1) == "-" ? "gid" : "uid", $owner_id, $img);
				if ($img)
					$attach[] = $img->response[0]->id;
				usleep($this->pause);
			}
			//обрезаем ссылки
			if ($del_links == 1)
				$message = preg_replace("!((https|http|ftp):\/\/)?(www\.)?([a-zA-Z0-9-а-яА-Я]*[\.])?[a-zA-Z0-9-а-яА-Я]*[\.]+[a-zA-Zа-яА-Я]{2,4}([\/a-zA-Z0-9-а-яА-Я])*!mi", "", $message);
			else if ($del_links == 2)  //оставляем только ссылки вконтакта
				$message = preg_replace("!((https|http|ftp):\/\/)?(www\.)?(vk\.com|vkontakte\.ru)([\/a-zA-Z0-9-а-яА-Я])*!mi", "", $message);
			//публикуем на стене запись
			$params = array(
				"owner_id"    => $owner_id,
				"message"     => $message,
				"attachments" => count($attach) ? implode(",", $attach) : "",
				"from_group"  => $from_group,
			);
			if ($time)
				$params["publish_date"] = $time;
			//echo "<pre>params=".print_r($params, 1).".</pre>";
			
			return $this->method("wall.post", $params);
		}

		/**
		 * Разместить комментарий к записи на стене.
		 * @param int $owner_id   - id пользователя/группы.
		 * @param int $post_id    - id записи.
		 * @param str $message    - текст комментария.
		 * @param int $from_group - 1: опубликовать от имени группы, 0: от своего.
		 *                          Только для группы (по-умолчанию: 1).
		 * @return str ответ от вконтакта в формате json.
		 */
		public function wall_add_comment ($owner_id, $post_id, $message, $from_group=1) {
			if (!$owner_id || !$post_id)
				return false;
		
			$params = array(
				"owner_id"   => $owner_id,
				"post_id"    => $post_id,
				"text"       => $message,
				"from_group" => $from_group,
			);
			
			return $this->method("wall.addComment", $params);
		}
}
