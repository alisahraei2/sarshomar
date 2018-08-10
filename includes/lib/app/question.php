<?php
namespace lib\app;

/**
 * Class for question.
 */
class question
{

	use question\add;
	use question\edit;
	use question\datalist;
	use question\dashboard;
	use question\type;
	use question\next;


	public static $raw_field =
	[
		'media',
		'setting',
		'choice',
	];


	public static function sort_choice($_args)
	{
		\dash\app::variable($_args);
		$sort = \dash\app::request('sort');
		if(!$sort || !is_array($sort))
		{
			\dash\notif::error(T_("No valid sort method sended!"));
			return false;
		}


		$survey_id = \dash\app::request('survey_id');
		$survey_id = \dash\coding::decode($survey_id);
		if(!$survey_id)
		{
			\dash\notif::error(T_("Survay id not set"), 'survey_id');
			return false;
		}

		$load_survey = \lib\db\surveys::get(['id' => $survey_id, 'limit' => 1]);
		if(!$load_survey || !isset($load_survey['user_id']))
		{
			\dash\notif::error(T_("Invalid survey id"), 'survey_id');
			return false;
		}

		if(intval(\dash\user::id()) !== intval($load_survey['user_id']))
		{
			if(!\dash\permission::supervisor())
			{
				\dash\log::db('isNotYourSurvay', ['data' => $survey_id]);
				\dash\notif::error(T_("This is not your survey!"), 'survey_id');
				return false;
			}
		}

		$block_survey = \lib\app\question::block_survey(\dash\app::request('survey_id'));

		if(count($block_survey) !== count($sort))
		{
			\dash\notif::error(T_("Some question was lost!"));
			return false;
		}

		$old_sort = array_column($block_survey, 'id');

		if($old_sort !== $sort)
		{
			$block_survey = array_combine($old_sort, $block_survey);

			$new_bloc_sort = [];
			foreach ($sort as $key => $value)
			{
				if(isset($block_survey[$value]))
				{
					$id = $block_survey[$value]['id'];
					$id = \dash\coding::decode($id);
					$new_bloc_sort[$key] = $id;
				}
				else
				{
					\dash\notif::error(T_("some data is incorrect!"));
					return false;
				}
			}

			\lib\db\questions::save_sort($new_bloc_sort);

		}

		\dash\notif::ok(T_("Sort question saved"));
		return true;

	}


	public static function get($_id)
	{
		$id = \dash\coding::decode($_id);
		if(!$id)
		{
			\dash\notif::error(T_("Survay id not set"));
			return false;
		}


		$get = \lib\db\questions::get(['id' => $id, 'limit' => 1]);

		if(!$get)
		{
			\dash\notif::error(T_("Invalid question id"));
			return false;
		}

		$result = self::ready($get);

		return $result;
	}


	public static function block_survey($_survey_id)
	{
		$survey_id = \dash\coding::decode($_survey_id);
		if(!$survey_id)
		{
			\dash\notif::error(T_("Survay id not set"));
			return false;
		}

		$result = \lib\db\questions::get_sort(['survey_id' => $survey_id]);

		if(is_array($result))
		{
			$result = array_map(['self', 'ready'], $result);
		}

		return $result;
	}


	/**
	 * check args
	 *
	 * @return     array|boolean  ( description_of_the_return_value )
	 */
	private static function check($_id = null)
	{
		$args            = [];

		$survey_id = \dash\app::request('survey_id');
		$survey_id = \dash\coding::decode($survey_id);
		if(!$survey_id)
		{
			\dash\notif::error(T_("Survay id not set"), 'survey_id');
			return false;
		}

		$load_survey = \lib\db\surveys::get(['id' => $survey_id, 'limit' => 1]);
		if(!$load_survey || !isset($load_survey['user_id']))
		{
			\dash\notif::error(T_("Invalid survey id"), 'survey_id');
			return false;
		}

		if(intval(\dash\user::id()) !== intval($load_survey['user_id']))
		{
			if(!\dash\permission::supervisor())
			{
				\dash\log::db('isNotYourSurvay', ['data' => $survey_id]);
				\dash\notif::error(T_("This is not your survey!"), 'survey_id');
				return false;
			}
		}

		if($_id)
		{
			$load_question = \lib\db\questions::get(['id' => $_id, 'survey_id' => $survey_id, 'limit' => 1]);
			if(!$load_question)
			{
				\dash\notif::error(T_("Invalid questions id"), 'survey_id');
				return false;
			}
		}

		$title   = \dash\app::request('title');
		$desc    = \dash\app::request('desc');
		$media   = \dash\app::request('media');
		$require = \dash\app::request('require') ? 1 : null;


		$type = \dash\app::request('type');
		if($type && mb_strlen($type) >= 200)
		{
			\dash\notif::error(T_("Please fill the question type less than 200 character"), 'type');
			return false;
		}

		$maxchar = \dash\app::request('maxchar');
		if($maxchar && !is_numeric($maxchar))
		{
			\dash\notif::error(T_("Please fill maxchar as a number"), 'maxchar');
			return false;
		}

		if($maxchar)
		{
			$maxchar = abs(intval($maxchar));
			if($maxchar > 1E+9)
			{
				\dash\notif::error(T_("Maxchart is out of range"), 'maxchar');
				return false;
			}
		}

		$sort = \dash\app::request('sort');
		if($sort && !is_numeric($sort))
		{
			\dash\notif::error(T_("Please fill the sort as a number"), 'sort');
			return false;
		}

		if($sort)
		{
			$sort = abs(intval($sort));
			if($sort > 1E+9)
			{
				\dash\notif::error(T_("Maxchart is out of range"), 'sort');
				return false;
			}
		}

		$status = \dash\app::request('status');
		if($status && !in_array($status, ['draft','publish','expire','deleted','lock','awaiting','question','filter','close', 'full']))
		{
			\dash\notif::error(T_("Invalid status of question"), 'status');
			return false;
		}

		if(is_array($media))
		{
			$media = json_encode($media, JSON_UNESCAPED_UNICODE);
		}

		$remove_choice = \dash\app::request('remove_choice');
		$add_choice    = \dash\app::request('add_choice');

		$choicetitle  = \dash\app::request('choicetitle');

		if($choicetitle && mb_strlen($choicetitle) > 10000)
		{
			$choicetitle = substr($choicetitle, 0, 10000);
		}

		$choicefile          = \dash\app::request('choicefile');

		if(\dash\app::isset_request('choicetitle') && $choicetitle !== '0' && !$choicetitle && !$choicefile)
		{
			\dash\notif::error(T_("Please fill the choice title"), 'choicetitle');
			return false;
		}

		$old_choice = [];


		if($add_choice || $remove_choice)
		{
			if(isset($load_question['choice']))
			{
				$old_choice = json_decode($load_question['choice'], true);
			}

			if(!is_array($old_choice))
			{
				$old_choice = [];
			}

			if($remove_choice)
			{
				$choice_key = \dash\app::request('choice_key');
				if(array_key_exists($choice_key, $old_choice))
				{
					unset($old_choice[$choice_key]);
				}
				else
				{
					\dash\notif::error(T_("Invalid choice key for remove"));
					return false;
				}
			}
			else
			{
				$new_choice          = [];
				$new_choice['title'] = $choicetitle;

				if($choicefile)
				{
					$new_choice['file'] = $choicefile;
				}

				$old_choice[] = $new_choice;
			}

			$choice         = json_encode($old_choice, JSON_UNESCAPED_UNICODE);
			$args['choice'] = $choice;
		}

		if(\dash\app::isset_request('random') || \dash\app::isset_request('otherchoice') || \dash\app::isset_request('buttontitle'))
		{
			$setting                = [];
			$setting['random']      = \dash\app::request('random') ? true : false;
			$setting['otherchoice'] = \dash\app::request('otherchoice') ? true : false;
			$buttontitle            = \dash\app::request('buttontitle');
			if($buttontitle && mb_strlen($buttontitle) > 10000)
			{
				$buttontitle = substr($buttontitle, 0, 10000);
			}

			if($buttontitle)
			{
				$buttontitle = \dash\safe::remove_nl($buttontitle);
			}

			$setting['buttontitle'] = $buttontitle;

			$args['setting'] = json_encode($setting, JSON_UNESCAPED_UNICODE);
		}

		$args['survey_id'] = $survey_id;
		$args['title']   = $title;
		$args['desc']    = $desc;
		$args['media']   = $media;
		$args['require'] = $require;
		$args['type']    = $type;
		$args['maxchar'] = $maxchar;
		$args['sort']    = $sort;
		$args['status']  = $status;

		return $args;
	}


	/**
	 * ready data of question to load in api
	 *
	 * @param      <type>  $_data  The data
	 */
	public static function ready($_data)
	{
		$result = [];
		foreach ($_data as $key => $value)
		{

			switch ($key)
			{
				case 'status':
					continue;
					break;

				case 'id':
				case 'user_id':
					$result[$key] = \dash\coding::encode($value);
					break;
				case 'type':
					$result[$key] = $value;
					$result['type_detail'] = self::get_type($value);
					break;

				case 'media':
				case 'choice':
				case 'setting':
					$result[$key] = json_decode($value, true);
					break;

				default:
					$result[$key] = $value;
					break;
			}
		}

		return $result;
	}

}
?>