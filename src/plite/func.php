<?php
/**
 * Copyright (c) 2012-2021 Troy Wu
 * Copyright (c) 2021      Version2 OÜ
 * All rights reserved.
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



namespace vertwo\plite;



function clog ( $s1 )
{
    switch ( func_num_args() )
    {
        case 2:
            Log::log(func_get_arg(0), func_get_arg(1));
            break;

        default:
            Log::log(func_get_arg(0));
            break;
    }
}


function cclog ( $color, $mesg ) { clog(Log::color($color, $mesg)); }


function redlog ( $mesg ) { cclog(Log::TEXT_COLOR_RED, $mesg); }


function yellog ( $mesg ) { cclog(Log::TEXT_COLOR_YELLOW, $mesg); }


function grnlog ( $mesg ) { cclog(Log::TEXT_COLOR_GREEN, $mesg); }


function cynlog ( $mesg ) { cclog(Log::TEXT_COLOR_CYAN, $mesg); }


function redulog ( $mesg ) { cclog(Log::TEXT_COLOR_UL_RED, $mesg); }


function yelulog ( $mesg ) { cclog(Log::TEXT_COLOR_UL_YELLOW, $mesg); }


function grnulog ( $mesg ) { cclog(Log::TEXT_COLOR_UL_GREEN, $mesg); }


function cynulog ( $mesg ) { cclog(Log::TEXT_COLOR_UL_CYAN, $mesg); }
