<?php

namespace frontend\controllers;

use app\models\Photo;
use Faker\Provider\Image;
use Yii;
use common\models\LoginForm;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\components\UserService;
use common\models\User;
use common\components;
use common\components\RelationService;
use common\components\RelationMode;
use common\components\RelationType;
use common\components\PhotoService;
use common\components\AccessService;
use common\components\RequestService;
use common\components\PostsService;
use common\components\RequestType;

class IntouchController extends Controller
{

	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::className(),
				'rules' => [
					[
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => VerbFilter::className(),
				'actions' => [
					'logout' => ['post'],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'error' => [
				'class' => 'yii\web\ErrorAction',
			],
			'captcha' => [
				'class' => 'yii\captcha\CaptchaAction',
				'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
			],
		];
	}

	public function actionIndex()
	{
		$id = Yii::$app->user->getId();
		$this->getUserData();
		//$zdjecie = new \app\models\Photo();
		//$dane = $zdjecie->find()->all();
		$this->layout = 'logged';

		if (Yii::$app->request->isPost || Yii::$app->request->isPjax)
		{
			if (!is_null(Yii::$app->request->post('type')))
			{
				switch (Yii::$app->request->post('type'))
				{
					case 'newpost':
						PostsService::createPost($id, Yii::$app->request->post('inputText'));
						break;

					case 'newcomment':
						PostsService::createComment(Yii::$app->request->post('post_id'),
							Yii::$app->request->post('inputText'));
						break;
				}
			}
		}


		$posts = PostsService::getFriendsPosts($id);
		//die(var_dump($posts));
		return $this->render('index', ['UserName' => $id, 'posts' => $posts]);
	}

	public function actionLoadMorePosts($last = 1)
	{
		$data = PostsService::getFriendsPosts(Yii::$app->user->id, $last);
		$lastId = $data[4]['post_id'];
		$id = Yii::$app->user->id;
		$html = $this->renderPartial('postsLoad', ['UserName' => $id, 'posts' => $data]);

		$arr['html'] = $html;
		$arr['lastId'] = $lastId;

		echo json_encode($arr);

	}

	public function actionTestmail()
	{
		if (Yii::$app->user->can('admin'))
		{
			$email = UserService::getEmail(1);
			$s = Yii::$app->mailer->compose()
				->setFrom('noreply@yii2.com')
				->setTo('costam@costa.com')
				->setSubject('InTouch')
				->setTextBody('Hello')
				->setHtmlBody('<b>Html Hello</b>')
				->send();
			die(var_dump($s));
		}
		else
		{
			echo "Access Denied, you have to log in as admin";
		}
		//
	}

	public function actionProfile()
	{
		$id = Yii::$app->user->getId();
		if (Yii::$app->request->isPost)
		{
			if (!is_null(Yii::$app->request->post('type')))
			{
				switch (Yii::$app->request->post('type'))
				{
					case 'settings':
						//To upload profile photo
						$plik = $_FILES['exampleInputFile']['tmp_name'];
						if (strlen($plik) > 0)
						{
							\common\components\PhotoService::setProfilePhoto($id, $plik);
						}

						UserService::setName($id, Yii::$app->request->post('inputName'));
						UserService::setSurname($id, Yii::$app->request->post('inputSurname'));
						USerService::setEmail($id, Yii::$app->request->post('inputEmail'));

						$pass1cnt = strlen(Yii::$app->request->post('inputPassword'));
						$pass2cnt = strlen(Yii::$app->request->post('inputPasswordRepeat'));
						if ($pass1cnt > 0 || $pass2cnt > 0)
						{
							if ($pass1cnt != $pass2cnt)
							{
								Yii::$app->session->setFlash('error',
									'Passwords not match. Password\'s has not been changed');
								return $this->redirect('/profile');
							}
							if ($pass1cnt < 6)
							{
								Yii::$app->session->setFlash('error', 'Password is too short');
								return $this->redirect('/profile');
							}
						}
						////////////////////

						Yii::$app->session->setFlash('success', 'Profile\'s been succesfuly updated');
						break;

					case 'newpost':
						PostsService::createPost($id, Yii::$app->request->post('inputText'));
						break;

					case 'newcomment':
						PostsService::createComment(Yii::$app->request->post('post_id'),
							Yii::$app->request->post('inputText'));
						break;
				}
			}
		}
		$education = UserService::getUserEducation($id);
		$about = UserService::getUserAbout($id);
		$city = UserService::getUserCity($id);
		$birth = UserService::getBirthDate($id);
		$name = UserService::getName($id);
		$surname = UserService::getSurname($id);
		$email = UserService::getEmail($id);
		$followers = count(RelationService::getUsersWhoFollowMe($id));
		$following = count(RelationService::getUsersWhoIFollow($id));
		$friends = count(RelationService::getFriendsList($id));
		$posts = PostsService::getPosts($id);
		$photo = PhotoService::getProfilePhoto($id, true, true);
		//////////////////////////////////////////////////////////////////////////
		$this->getUserData();
		$this->layout = 'logged';
		return $this->render('profile', [
			'name' => $name,
			'surname' => $surname,
			'email' => $email,
			'education' => $education,
			'about' => $about,
			'city' => $city,
			'birth' => $birth,
			'followers' => $followers,
			'following' => $following,
			'friends' => $friends,
			'posts' => $posts,
			'photo' => $photo,
			'id' => $id,
		]);
	}

	public function actionAboutedit()
	{
		$id = Yii::$app->user->getId();
		////////////////////////////

		$education = UserService::getUserEducation($id);
		$about = UserService::getUserAbout($id);
		$city = UserService::getUserCity($id);
		$birth = UserService::getBirthDate($id);

		if (Yii::$app->request->isPost)
		{
			UserService::setUserCity($id, Yii::$app->request->post('inputLocation'));
			UserService::setUserEducation($id, Yii::$app->request->post('inputEducation'));
			UserService::setUserAbout($id, Yii::$app->request->post('inputNotes'));

			try
			{
				$bdate = Yii::$app->request->post('inputDate');
				if (strtotime($bdate) - time() > 0)
				{
					Yii::$app->session->setFlash('error', 'Hello! It\'s date from future!');
					return $this->redirect('/profile/aboutedit');
				}
				UserService::setBirthDate($id, $bdate);
			}
			catch (\common\components\exceptions\InvalidDateException $e)
			{
				Yii::$app->session->setFlash('error', 'Invalid date');
				return $this->redirect('/profile/aboutedit');
			}

			Yii::$app->session->setFlash('success', 'Profile\'s been Succesfuly Updated');
			//UserService::setUserAbout($id, Yii::$app->request->post('inputNotes'));
			return $this->redirect('/profile');
		}


		///////////////////////////
		$this->getUserData();
		$this->layout = 'logged';
		return $this->render('aboutEdit', [
			'education' => $education,
			'about' => $about,
			'city' => $city,
			'birth' => $birth
		]);
	}

	public function getUserData()
	{
		$id = Yii::$app->user->getId();

		$photo = PhotoService::getProfilePhoto($id);
		$this->view->params['userProfilePhoto'] = $photo;

		$userinfo = array();
		$userinfo['user_name'] = UserService::getName($id);
		$userinfo['user_surname'] = UserService::getSurname($id);
		if ($userinfo['user_name'] == false)
		{
			$userinfo['user_name'] = "Uzupełnij";
		}
		if ($userinfo['user_surname'] == false)
		{
			$userinfo['user_surname'] = "swoje dane";
		}

		$this->view->params['userInfo'] = $userinfo;
		////////////////////////////////////////////////////// request service

		$notification = RequestService::getMyRequests($id);
		$tablelength = count($notification);
		$this->view->params['notification_data'] = $notification;
		$this->view->params['notification_count'] = $tablelength;
	}

	public function actionSearch($q)
	{
		if (Yii::$app->user->can('search-use'))
		{
			$id = Yii::$app->user->getId();
			$this->getUserData($id);
			$this->layout = 'logged';
			$users = \common\components\SearchService::findUsers($q);
			$resultsCnt = count($users);
			return $this->render('searchResults', [
				'query' => $q,
				'count' => $resultsCnt,
				'users' => $users,
			]);
		}
		else
		{
			$this->redirect("/intouch/accessdenied");
		}
	}

	public function actionAccessdenied()
	{
		$id = Yii::$app->user->getId();
		$this->getUserData($id);
		$this->layout = 'logged';
		return $this->render('accessDenied');
	}

	public function actionNotifications()
	{
		$id = Yii::$app->user->getId();


		if (Yii::$app->request->isPost)
		{
			if (!is_null(Yii::$app->request->post('accept-btn')) || !is_null(Yii::$app->request->post('dismiss-btn')))
			{
				$answer = false;
				if (!is_null(Yii::$app->request->post('accept-btn')))
				{
					$answer = true;
				}
				$request_id = Yii::$app->request->post('request_id');
				RequestService::answerRequest($request_id, $answer);
			}
		}

		$this->getUserData($id);
		$this->layout = 'logged';
		return $this->render('allRequests');
	}

	public function actionMyfriends()
	{
		$id = Yii::$app->user->getId();
		///////
		$friends = RelationService::getFriendsList($id, true);
		$falone =
			new components\Image("forever_alone.png", new components\ImageTypes(components\ImageTypes::InTouchImage),
				new components\ImgLocations\ImgIntouch());
		$faloneT = new components\Image("forever_alone_text.png",
			new components\ImageTypes(components\ImageTypes::InTouchImage), new components\ImgLocations\ImgIntouch());
		///////
		$this->getUserData($id);
		$this->layout = 'logged';
		return $this->render('myFriends', ['friends' => $friends, 'imgForeverAlone' => $falone->getImage(),
		                                   'imgForeverAloneText' => $faloneT->getImage()]);
	}

}
