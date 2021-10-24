<?php declare(strict_types = 1);



/**
 * Copyright (c) 2012-2021 Troy Wu
 * Copyright (c) 2021      Version2 OÜ
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */



use PHPUnit\Framework\TestCase;
use vertwo\plite\STELAR\Ball;



final class BallTest extends TestCase
{
    const DEBUG = false;

    const JSON_STRING = <<<EOF
{
  "accounting": [
    {
      "firstName": "John",
      "lastName": "Doe",
      "age": 23
    },
    {
      "firstName": "Mary",
      "lastName": "Smith",
      "age": 32
    }
  ],
  "sales": [
    {
      "firstName": "Sally",
      "lastName": "Green",
      "age": 27
    },
    {
      "firstName": "Jim",
      "lastName": "Galley",
      "age": 41
    }
  ]
}
EOF;

    const JSON_STRING_WITH_EMPTY = <<<EOF
{
  "accounting": [
    {
      "firstName": "John",
      "lastName": "Doe",
      "age": 23,
      "isActive" : true
    },
    {
      "firstName": "Mary",
      "lastName": "Smith",
      "age": 32,
      "isActive" : false
    }
  ],
  "engineering": [
  ],
  "sales": [
    {
      "firstName": "Sally",
      "lastName": "Green",
      "age": 27,
      "isActive" : false
    },
    {
      "firstName": "Jim",
      "lastName": "Galley",
      "age": 41,
      "isActive" : true
    }
  ]
}
EOF;

    const EXPECTED_KEYS_LINES = <<<EOF
accounting[0].firstName
accounting[0].lastName
accounting[0].age
accounting[0].isActive
accounting[1].firstName
accounting[1].lastName
accounting[1].age
accounting[1].isActive
engineering
sales[0].firstName
sales[0].lastName
sales[0].age
sales[0].isActive
sales[1].firstName
sales[1].lastName
sales[1].age
sales[1].isActive
EOF;

    const KEY_1 = "p.q.r.s.t";
    const VALUE = "test-abc-123";
    const KEY_2 = "alksjdfkljakdjsfkajdf.qqzz";



    public function testGetArray ()
    {
        $ball = Ball::fromJSON(self::JSON_STRING);
        $key  = $ball->get("sales[0].firstName");

        $this->assertEquals($key, "Sally");
    }


    public function testGetBasic ()
    {
        $ball = Ball::fromJSON(self::JSON_STRING);
        $ball->set(self::KEY_1, self::VALUE);

        $key = $ball->get(self::KEY_1);

        $this->assertEquals(self::VALUE, $key);
    }


    public function testSwap ()
    {
        $ball = Ball::fromJSON(self::JSON_STRING);
        $ball->swap("sales[0].firstName", "accounting[1].age");
        $salesFirst    = $ball->get("sales[0].firstName");
        $accountingAge = $ball->get("accounting[1].age");

        if ( self::DEBUG ) $ball->dump();

        $this->assertEquals($salesFirst, 32);
        $this->assertEquals($accountingAge, "Sally");
    }


    public function testDelete ()
    {
        $ball = Ball::fromJSON(self::JSON_STRING);
        $ball->delete("accounting[1].lastName");
        $this->assertFalse($ball->has("accounting[1].lastName"));
    }


    public function testMove ()
    {
        $ball = Ball::fromJSON(self::JSON_STRING);
        $ball->set(self::KEY_1, self::VALUE);

        $key = $ball->get(self::KEY_1);

        $this->assertEquals(self::VALUE, $key);

        $ball->move(self::KEY_1, self::KEY_2);

        $val = $ball->get(self::KEY_2);

        $this->assertEquals(self::VALUE, $val);
    }


    public function testEmptyKey ()
    {
        $ball = Ball::fromJSON(self::JSON_STRING_WITH_EMPTY);

        $this->assertTrue($ball->has("engineering"));

        $eng = $ball->get("engineering");

        $this->assertTrue(is_array($eng));
        $this->assertCount(0, $eng);
    }


    public function testIterator ()
    {
        $expKeys = explode("\n", self::EXPECTED_KEYS_LINES);
        $k       = 0;

        $ball = Ball::fromJSON(self::JSON_STRING_WITH_EMPTY);
        $iter = $ball->iterator();

        foreach ( $iter as $key => $val )
        {
            $this->assertEquals($expKeys[$k], $key);
            ++$k;
        }
    }
}
