<?php
/**
 * Message class file.
 *
 * @author Jonah Turnquist <poppitypop@gmail.com>
 * @link http://www.yiiframework.com/
 */
 
/**
* Any requests to set or get attributes or call methods on this class that are not found in that class are redirected to
* the Swift_Message object.
* 
* This means you need to look at the SwiftMailer documentation to see what methods are availiable for this class.  There are
* a *lot* of methods, more than I with to document.  Any methods availiable in Swift_Mime_Message are availiable here.
* 
* Documentation for the most important methods can be found at {@link http://swiftmailer.org/docs/messages}
* 
* The Message component also allows using a shorthand for methods in Swift_Mime_Message that start with set* or get*
* For instance, instead of calling <pre>$message->setFrom(...);</pre> you can use <pre>$message->from = '...'</pre>.
* 
* Here are a few methods to get you started:
* * setSubject('Your subject')
* * setFrom(array('john@doe.com' => 'John Doe'))
* * setTo(array('receiver@domain.org', 'other@domain.org' => 'A name'))
* * attach(Swift_Attachment::fromPath('my-document.pdf'))
*/
class Message extends CComponent {
	
	/**
	* @var string the view to use for rendering the body, null if no view is used.  An extra variable $mail will be passed to the
	* view which you may use to set e.g. the email subject from within the view
	*/
	public $view;

	/**
	* @var Swift_Message
	*/
	public $message;
	
	/**
	* Any requests to set or get attributes or call methods on this class that are not found are redirected to the Swift_Message object
	* @param string the attribute name
	*/
	public function __get($name) {
		try {
			return parent::__get($name);
		} catch (CException $e) {
			$getter='get'.$name;
			if(method_exists($this->message,$getter))
				return $this->message->$getter();
			else
				throw $e;		
		}
	}
	
	/**
	* Any requests to set or get attributes or call methods on this class that are not found are redirected to the Swift_Message object
	* @param string the attribute name
	*/
	public function __set($name,$value) {
		try {
			return parent::__set($name,$value);
		} catch (CException $e) {
			$setter='set'.$name;
			if(method_exists($this->message,$setter))
				$this->message->$setter($value);
			else
				throw $e;		
		}
	}
	
	/**
	* Any requests to set or get attributes or call methods on this class that are not found are redirected to the Swift_Message object
	* @param string the method name
	*/
	public function __call($name,$parameters) {
		try {
			return parent::__call($name,$parameters);	
		} catch (CException $e) {
			if(method_exists($this->message,$name))
				return call_user_func_array(array($this->message,$name),$parameters);
			else
				throw $e;
		}
	}
	
	/**
	* You may optionally set some message info using the paramaters of this constructor.
	* Use {@link view} and {@link setBody()} for more control
	* 
	* @param string $subject
	* @param string $body
	* @param string $contentType
	* @param string $charset
	* @return Swift_Mime_Message
	*/
	public function __construct($subject = null, $body = null, $contentType = null, $charset = null) {
		Yii::app()->mail->registerScripts();
		$this->message = Swift_Message::newInstance($subject, $body, $contentType, $charset);
	}
	
	/**
	* Set the body of this entity, either as a string, or array of view variables if a view is set, or as an instance of
	* {@link Swift_OutputByteStream}.
	* 
	* @param mixed the body of the message.  If a $this->view is set and this is a string, this is passed to the view as $body.
	* If $this->view is set and this is an array, the array values are passed to the view like in the controller render() method
	* @param string content type optional. For html, set to 'html/text'
	* @param string charset optional
	*/
	public function setBody($body='', $contentType = null, $charset = null) {
		if ($this->view !== null) {
			if (!is_array($body))
				$body = array('body'=>$body);
      if (strpos($this->view, '/') !== false) {
        $view = $this->view;
      } else {
        $view = Yii::app()->mail->viewPath.'.'.$this->view;
      }
      $body = $this->renderInternal(Yii::getPathOfAlias($view).'.php', array_merge($body, array('mail'=>$this)), true);
      $layout = Yii::app()->mail->layout;
      if (stripos(Yii::app()->mail->layout, '.') === false) $layout = 'application.views.layouts.' . $layout;
      $layoutPath = Yii::getPathOfAlias($layout);
      if ($layoutPath!==false) {
        $body = $this->renderInternal($layoutPath.'.php', array_merge(array('content'=>$body), array('mail'=>$this)), true);
      }
      Yii::trace("Mail Body: " . $body);
		}
		return $this->message->setBody($body, $contentType, $charset);
	}

  protected function renderInternal($_viewFile_, $variable=array(), $_return_=false) {
    // we use special variable names here to avoid conflict when extracting data
    if(is_array($variable))
      extract($variable,EXTR_PREFIX_SAME,'data');
    else
      $data=$variable;
    if($_return_)
    {
      ob_start();
      ob_implicit_flush(false);
      require($_viewFile_);
      return ob_get_clean();
    }
    else
      require($_viewFile_);
  }
}