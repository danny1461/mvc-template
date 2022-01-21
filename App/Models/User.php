<?php

namespace App\Models;

use Exception;
use Lib\Database\Model;

/**
 * @Table('users')
 */
class User extends Model {
	private static $currentUser = false;

	/**
	 * @Key
	 * @AutoIncrement
	 */
	public $id;

	public $email;

	/**
	 * @Column('first_name')
	 */
	public $firstName;

	/**
	 * @Column('last_name')
	 */
	public $lastName;

	public $password;

	/**
	 * @Column('password_salt')
	 */
	public $passwordSalt;

	/**
	 * @Column('activation_key')
	 */
	public $activationKey;

	/**
	 * @Column('updated_at')
	 */
	public $updatedAt;

	/**
	 * @Column('created_at')
	 */
	public $createdAt;

	/**
	 * Concatenates the full name
	 *
	 * @return string
	 */
	public function getFullName() {
		return trim($this->firstName . ' ' . $this->lastName);
	}

	/**
	 * Returns whether the user is the logged in user
	 *
	 * @return boolean
	 */
	public function isLoggedIn() {
		return (isset($_SESSION['current_user']) && $this->doesExist() && ($this->id == $_SESSION['current_user']));
	}

	/**
	 * Fetches the current logged in user
	 *
	 * @return User|null
	 */
	public static function getCurrentUser() {
		if (self::$currentUser === false) { 
			if (isset($_SESSION['current_user'])) { //If a user is in session? 
				self::$currentUser = User::getByKey($_SESSION['current_user']); //Then assign user to current user? 
			}
			else {
				self::$currentUser = null; //There is no current user 
			}
		}

		return self::$currentUser;
	}

	/**
	 * Logs in the given user
	 *
	 * @param User $userModel
	 * @return void
	 */
	public static function loginUser(User $userModel) {
		if (!$userModel->doesExist()) {
			throw new Exception('User model has not been saved yet');
		}

		self::$currentUser = $userModel;
		$_SESSION['current_user'] = $userModel->id; 
	}

	/**
	 * Logs out the current user
	 *
	 * @return void
	 */
	public static function loggoutUser() {
		self::$currentUser = null;
		unset($_SESSION['current_user']);
	}
}