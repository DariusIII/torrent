<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 5-12-13
 * Time: 17:20
 */

namespace Devristo\Torrent;

class Bee {

    private function encode_string($string): string
    {
        return sprintf("%d:%s", strlen($string), $string);
    }

    private function encode_int($int): string
    {
        return 'i'.$int.'e';
    }

    private function encode_list(array $list): string
    {
        $encoded = 'l';

        foreach($list as $item)
            $encoded .= $this->encode($item);

        $encoded .= 'e';

        return $encoded;
    }

    private function encode_dict(array $dict): string
    {
        $encoded = 'd';

        ksort($dict, SORT_STRING);

        foreach($dict as $key => $value){
            $encoded .= $this->encode_string($key);
            $encoded .= $this->encode($value);
        }

        $encoded .= 'e';
        return $encoded;
    }
	
	private function is_list(array $arr): bool
	{
		return array_is_list($arr);
	}

    private function is_dict(array $arr): bool
    {
        for (reset($arr); is_int(key($arr)) || is_string(key($arr)); next($arr));
        return is_null(key($arr));
    }

    /**
     * @param $object
     * @return string
     */
    public function encode($object): string
    {
        if(is_int($object) || ctype_digit($object))
            return $this->encode_int($object);
        elseif(is_string($object))
            return $this->encode_string($object);
        elseif(is_array($object))
            if($this->is_list($object))
                return $this->encode_list($object);
            elseif($this->is_dict($object))
                return $this->encode_dict($object);
            else throw new \InvalidArgumentException("Input is not valid");
        else throw new \InvalidArgumentException("Input is not valid");
    }


    public function eatInt(&$string, &$pos): string
    {
        // Eat the i
        $pos++;

        $i = $pos;
        while($i < strlen($string)){
            if(ctype_digit($string[$i]) || $string[$i] == '-')
                $i++;
            elseif($string[$i] == 'e'){
                $result = substr($string, $pos, $i-$pos);
                $pos = $i+1;
                return $result;
            }else
                break;
        }

        throw new \InvalidArgumentException("Invalid int format");
    }

    public function eatList(&$string, &$pos): array
    {
        // Eat the l
        $pos++;

        $i = $pos;
        $items = array();
        while($i < strlen($string)){

            if($string[$i] == 'e'){
                $pos = $i+1;
                return $items;
            }else {
                $items[] = $this->decode($string, $i);
            }
        }

        throw new \InvalidArgumentException("Invalid list format");
    }

    public function eatDict(&$string, &$pos): array
    {
        // Eat the d
        $pos++;

        $i = $pos;
        $items = array();
        while($i < strlen($string)){

            if($string[$i] == 'e'){
                $pos = $i+1;
                return $items;
            }else {
                $key = $this->decode($string, $i);
                $value = $this->decode($string, $i);

                $items[$key] = $value;
            }
        }

        throw new \InvalidArgumentException("Invalid dict format");
    }

    public function eatString(&$string, &$pos): string
    {
        $i = $pos;
        while($i < strlen($string)){
            if(ctype_digit($string[$i]))
                $i++;
            elseif($string[$i] == ':'){
                $length = (int)substr($string, $pos, $i-$pos);

                if($length + $i < strlen($string)){
                    $result = substr($string, $i+1, $length);
                    $pos = $length + $i + 1;
                    return $result;
                }else break;

            }else
                break;
        }
        throw new \InvalidArgumentException("Invalid string format");
    }
	
	/**
	 * @param $string
	 * @param  int  $pos
	 * @return array|string|void
	 */
	public function decode($string, int &$pos=0)
    {
		while($pos < strlen($string)){
            switch($string[$pos]){
                case 'i':
                    return $this->eatInt($string, $pos);
                case 'l':
                    return $this->eatList($string, $pos);
                case 'd':
                    return $this->eatDict($string, $pos);
                default:
                    if(ctype_digit($string[$pos]))
                        return $this->eatString($string, $pos);
                    else throw new \InvalidArgumentException("Invalid input format");

            }
        }
    }
}