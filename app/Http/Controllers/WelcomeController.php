<?php

namespace App\Http\Controllers;
use Auth;
use DB;
use Illuminate\Http\Request;
use App\Exports\UsersExport;
use App\Imports\UsersImport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests;
use Validator;
use Maatwebsite\Excel\Facades\Excel;
class WelcomeController extends Controller
{
    //compose
    public function index()
    {
        return view('welcome'); //landing page
    }
    
    function csvToArray($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename))
            return false;

        $header = null;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== false)
        {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false)
            {
                try {
                    if (!$header)
                        $header = $row;
                    else
                        $data[] = array_combine($header, $row);
                } catch (\Exception $e) {
                    var_dump("Error file");
                    return false;
                    //return false;
                }

            }
            fclose($handle);
        }

        return $data;
    }

    public function upload(Request $request){
        $array = Excel::toArray(new UsersImport,$request->file('csvfile'));
        $array4 = $array[4];
        $headers = [
                'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0'
            ,   'Content-type'        => 'text/csv'
            ,   'Content-Disposition' => 'attachment; filename=temp.csv'
            ,   'Expires'             => '0'
            ,   'Pragma'              => 'public'
        ];
        
        for($i=1;$i<count($array4);$i++)
        {
            $array4[$i][3] = "";
            $array4[$i][2] = "";
            $array4[$i][0] = date('Y-m-d');
            if(isset($array4[$i][1])&&$array4[$i][1]!=NULL)
            {
                $str = $array4[$i][1];
                $str = str_replace(",","",$str);
                $str = str_replace(" ","%20",$str);
                $str = str_replace("#","%23",$str);
                $str = $str."&";//
                
                $url = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input=".$str."inputtype=textquery&fields=user_ratings_total,rating&key=AIzaSyCADwssqYbXeSDyLewE0vuZ5QdzOo14kxE";               
                $rating = json_decode(file_get_contents($url), true);
                if($rating['status'] == "OK")
                {
                    if (isset($rating['candidates'][0]['rating']))
                        $array4[$i][3] = $rating['candidates'][0]['rating'];
                    else
                        $array4[$i][3] = "";
                    if (isset($rating['candidates'][0]['user_ratings_total']))
                        $array4[$i][2] = $rating['candidates'][0]['user_ratings_total'];
                    else
                        $array4[$i][2] = "";
                    $array[4] = $array4;
                }
                else if($rating['status'] == "OVER_QUERY_LIMIT")
                {
                    $i--;
                }                
            }
        }
        $callback = function() use ($array4)
        {
            $FH = fopen('php://output', 'w');
            foreach ($array4 as $row) {
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        $response = new StreamedResponse($callback, 200, $headers);
        $response->send();
    }
    public function export()
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }
}
