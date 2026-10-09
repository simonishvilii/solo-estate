<?php
/**
 * Front-end interface strings, editable per language in wp-admin.
 *
 * Resolution order: value saved in Solo Estate → Settings → Localization for the language,
 * then the built-in default for that language (ka/ru/en), then the gettext translation.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Texts {

	const OPTION = 'solo_estate_texts';

	/**
	 * Registry: key => [English, Georgian, Russian].
	 *
	 * Plain strings on purpose (no gettext): the value for a language must not depend on the
	 * admin's or visitor's locale. Other languages are set under Solo Estate → Settings → Localization.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function registry() {
		return array(
			'projects'        => array( 'Projects', 'პროექტები', 'Проекты' ),
			'project'         => array( 'Project', 'პროექტი', 'Проект' ),
			'all'             => array( 'All', 'ყველა', 'Все' ),
			'filter_title'    => array( 'Find an apartment', 'ბინის ძიება', 'Подбор квартиры' ),
			'rooms'           => array( 'Rooms', 'ოთახები', 'Комнаты' ),
			'studio'          => array( 'Studio', 'სტუდიო', 'Студия' ),
			'any'             => array( 'Any', 'ნებისმიერი', 'Любой' ),
			'from'            => array( 'from %s', '%s-დან', 'от %s' ),
			'to'              => array( 'to %s', '%s-მდე', 'до %s' ),
			'only_available'  => array( 'Only available', 'მხოლოდ თავისუფალი', 'Только свободные' ),
			'search'          => array( 'Search', 'ძიება', 'Найти' ),
			'reset'           => array( 'Reset', 'გასუფთავება', 'Сбросить' ),
			'found'           => array( 'Apartments found: %d', 'ნაპოვნია ბინა: %d', 'Найдено квартир: %d' ),
			'no_results'      => array( 'No apartments match these filters. Try widening them.', 'ფილტრის შესაბამისი ბინა ვერ მოიძებნა. სცადეთ პარამეტრების შეცვლა.', 'Квартир по этим параметрам нет. Попробуйте расширить поиск.' ),
			'sort'            => array( 'Sort', 'დალაგება', 'Сортировка' ),
			'sort_default'    => array( 'By building and floor', 'კორპუსით და სართულით', 'По корпусу и этажу' ),
			'sort_price_asc'  => array( 'Price: low to high', 'ფასი: ზრდადობით', 'Цена: по возрастанию' ),
			'sort_price_desc' => array( 'Price: high to low', 'ფასი: კლებადობით', 'Цена: по убыванию' ),
			'sort_area_asc'   => array( 'Area: small to large', 'ფართი: ზრდადობით', 'Площадь: по возрастанию' ),
			'sort_area_desc'  => array( 'Area: large to small', 'ფართი: კლებადობით', 'Площадь: по убыванию' ),
			'building'        => array( 'Building', 'კორპუსი', 'Корпус' ),
			'floor'           => array( 'Floor', 'სართული', 'Этаж' ),
			'floors'          => array( 'Floors', 'სართულები', 'Этажи' ),
			'flat'            => array( 'Apartment', 'ბინა', 'Квартира' ),
			'flats'           => array( 'Apartments', 'ბინები', 'Квартиры' ),
			'phase'           => array( 'Phase', 'ფაზა', 'Очередь' ),
			'entrance'        => array( 'Entrance', 'სადარბაზო', 'Подъезд' ),
			'parking'         => array( 'Parking', 'პარკინგი', 'Паркинг' ),
			'commercial'      => array( 'Commercial space', 'კომერციული ფართი', 'Коммерческое помещение' ),
			'villa'           => array( 'Villa', 'ვილა', 'Вилла' ),
			'spot'            => array( 'Parking space', 'პარკინგის ადგილი', 'Машиноместо' ),
			'living_area'     => array( 'Living area', 'საცხოვრებელი ფართი', 'Жилая площадь' ),
			'summer_area'     => array( 'Summer area', 'საზაფხულო ფართი', 'Летняя площадь' ),
			'sold_percent'    => array( 'Sold: %d%%', 'გაყიდულია: %d%%', 'Продано: %d%%' ),
			'virtual_tour'    => array( 'Virtual tour', 'ვირტუალური ტური', 'Виртуальный тур' ),
			'previous'        => array( 'Previous', 'წინა', 'Назад' ),
			'next'            => array( 'Next', 'შემდეგი', 'Далее' ),
			'photos'          => array( 'Photos', 'ფოტოები', 'Фотографии' ),
			'photo_n'         => array( 'Photo %d', 'ფოტო %d', 'Фото %d' ),
			'breadcrumbs'     => array( 'You are here', 'თქვენ აქ ხართ', 'Вы здесь' ),
			'currency'        => array( 'Currency', 'ვალუტა', 'Валюта' ),
			'show'            => array( 'Show', 'ჩვენება', 'Показать' ),
			'gallery'         => array( 'Gallery', 'გალერეა', 'Галерея' ),
			'plan_2d'         => array( '2D plan', '2D გეგმა', '2D планировка' ),
			'plan_3d'         => array( '3D plan', '3D გეგმა', '3D планировка' ),
			'select_item'     => array( 'Select', 'აირჩიეთ', 'Выберите' ),
			'status'          => array( 'Status', 'სტატუსი', 'Статус' ),
			'area'            => array( 'Area', 'ფართი', 'Площадь' ),
			'total_area'      => array( 'Total area', 'საერთო ფართი', 'Общая площадь' ),
			'price'           => array( 'Price', 'ფასი', 'Цена' ),
			'price_sqm'       => array( 'Price per m²', 'ფასი 1 მ²', 'Цена за м²' ),
			'sold_out'        => array( 'Sold out', 'გაყიდულია', 'Продано' ),
			'available_label' => array( 'Available', 'თავისუფალი', 'Свободно' ),
			'sold_label'      => array( 'Sold', 'გაყიდული', 'Продано' ),
			'click_hint'      => array( 'Click for details', 'დააჭირეთ დეტალებისთვის', 'Нажмите, чтобы открыть' ),
			'completion'      => array( 'Completion', 'დასრულება', 'Сдача' ),
			'available_count' => array( 'Available: %d', 'თავისუფალი: %d', 'Свободно: %d' ),
			'details'         => array( 'Details', 'დეტალურად', 'Подробнее' ),
			'close'           => array( 'Close', 'დახურვა', 'Закрыть' ),
			'back'            => array( 'Back', 'უკან', 'Назад' ),
			'fullscreen'      => array( 'Full screen', 'სრულ ეკრანზე', 'Во весь экран' ),
			'fullscreen_exit' => array( 'Exit full screen', 'გამოსვლა', 'Выйти' ),
			'specs'           => array( 'Specification', 'სპეციფიკაცია', 'Спецификация' ),
			'plan'            => array( 'Plan', 'გეგმა', 'Планировка' ),
			'select_floor'    => array( 'Select a floor', 'აირჩიეთ სართული', 'Выберите этаж' ),
			'select_flat'     => array( 'Select an apartment', 'აირჩიეთ ბინა', 'Выберите квартиру' ),
			'lead_title'      => array( 'Request a call', 'მოითხოვეთ ზარი', 'Заказать звонок' ),
			'lead_note'       => array( 'Leave your phone number and our sales team will call you back.', 'დატოვეთ ნომერი და გაყიდვების გუნდი დაგიკავშირდებათ.', 'Оставьте номер, и отдел продаж вам перезвонит.' ),
			'lead_name'       => array( 'Name', 'სახელი', 'Имя' ),
			'lead_phone'      => array( 'Phone', 'ტელეფონი', 'Телефон' ),
			'lead_submit'     => array( 'Send', 'გაგზავნა', 'Отправить' ),
			'lead_name_error' => array( 'Please enter your name.', 'გთხოვთ, მიუთითოთ სახელი.', 'Укажите, пожалуйста, имя.' ),
			'lead_phone_error' => array( 'Please enter a phone number (at least 6 digits).', 'გთხოვთ, მიუთითოთ ტელეფონის ნომერი (მინიმუმ 6 ციფრი).', 'Укажите номер телефона (не менее 6 цифр).' ),
			'lead_success'    => array( 'Thank you, your request has been received. Our representative will contact you within 24 hours.', 'მადლობა, თქვენი მოთხოვნა მიღებულია. ჩვენი წარმომადგენელი 24 საათის განმავლობაში დაგიკავშირდებათ.', 'Спасибо, ваш запрос принят. Наш представитель свяжется с вами в течение 24 часов.' ),
			'lead_error'      => array( 'Something went wrong. Please check the fields and try again.', 'დაფიქსირდა შეცდომა. გთხოვთ, შეამოწმოთ ველები და სცადოთ ხელახლა.', 'Произошла ошибка. Проверьте поля и попробуйте ещё раз.' ),
			'no_items'        => array( 'Nothing to show yet.', 'ჯერ არაფერია დამატებული.', 'Пока ничего нет.' ),
		);
	}

	/**
	 * Translated string for the current (or given) language.
	 *
	 * @param string      $key  Registry key.
	 * @param string|null $lang Language code.
	 * @return string
	 */
	public static function get( $key, $lang = null ) {
		$lang  = $lang ? $lang : I18n::current();
		$saved = get_option( self::OPTION, array() );

		if ( isset( $saved[ $key ][ $lang ] ) && '' !== $saved[ $key ][ $lang ] ) {
			return (string) $saved[ $key ][ $lang ];
		}
		return self::builtin( $key, $lang );
	}

	/**
	 * Built-in default for a language.
	 *
	 * @param string $key  Registry key.
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function builtin( $key, $lang ) {
		$registry = self::registry();
		if ( ! isset( $registry[ $key ] ) ) {
			return $key;
		}
		$languages = I18n::languages();
		$locale    = isset( $languages[ $lang ] ) ? $languages[ $lang ]['locale'] : '';
		$prefix    = strtolower( substr( $locale ? $locale : $lang, 0, 2 ) );

		if ( 'ka' === $prefix || 'ge' === $lang ) {
			return $registry[ $key ][1];
		}
		if ( 'ru' === $prefix ) {
			return $registry[ $key ][2];
		}
		return $registry[ $key ][0];
	}

	/**
	 * Stores overrides from the settings screen.
	 *
	 * @param array $input key => [lang => value].
	 */
	public static function save( array $input ) {
		$out = array();
		foreach ( array_keys( self::registry() ) as $key ) {
			foreach ( I18n::codes() as $lang ) {
				if ( ! isset( $input[ $key ][ $lang ] ) ) {
					continue;
				}
				$value = sanitize_text_field( wp_unslash( $input[ $key ][ $lang ] ) );
				// Only store real overrides so built-in defaults keep improving with updates.
				if ( '' !== $value && self::builtin( $key, $lang ) !== $value ) {
					$out[ $key ][ $lang ] = $value;
				}
			}
		}
		update_option( self::OPTION, $out );
		Nodes::changed( 'texts' );
	}

	/**
	 * Updates the given keys only (Settings → Localization saves one page at a time).
	 *
	 * @param array<string,array<string,string>> $rows Key => [lang code => unslashed text]; empty resets to the default.
	 */
	public static function update( array $rows ) {
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$registry = self::registry();
		foreach ( $rows as $key => $values ) {
			if ( ! isset( $registry[ $key ] ) ) {
				continue;
			}
			foreach ( I18n::codes() as $lang ) {
				$value = isset( $values[ $lang ] ) ? sanitize_text_field( $values[ $lang ] ) : '';
				// Only store real overrides so built-in defaults keep improving with updates.
				if ( '' !== $value && self::builtin( $key, $lang ) !== $value ) {
					$saved[ $key ][ $lang ] = $value;
				} else {
					unset( $saved[ $key ][ $lang ] );
				}
			}
			if ( empty( $saved[ $key ] ) ) {
				unset( $saved[ $key ] );
			}
		}
		update_option( self::OPTION, $saved );
		Nodes::changed( 'texts' );
	}
}
