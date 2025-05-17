<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Jyotish\Lib;
use Psr\Log\LoggerInterface;
use OpenApi\Annotations as OA;
use App\Exception\ApiException;

class APIController extends AbstractController
{
    private LoggerInterface $logger;
    private Lib $chart;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->chart = new Lib();
    }

    /**
     * @Route("/api/ping", name="ping", methods={"GET"})
     * @OA\Get(
     *     path="/api/ping",
     *     summary="Health check endpoint",
     *     tags={"System"},
     *     @OA\Response(
     *         response=200,
     *         description="Success response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="pong", type="string", example="success")
     *         )
     *     )
     * )
     */
    public function ping(Request $request)
    {
        return $this->json([
            'pong' => "success",
        ], 200);
    }

    /**
     * @Route("/api/calculate", name="calculate", methods={"GET"})
     * @OA\Get(
     *     path="/api/calculate",
     *     summary="Calculate astrological chart",
     *     description="Calculates an astrological chart based on provided parameters",
     *     tags={"Chart"}
     * )
     * @OA\Parameter(
     *     name="latitude",
     *     in="query",
     *     description="Location latitude",
     *     required=true,
     *     @OA\Schema(type="number", format="float", example=28.6139)
     * )
     * @OA\Parameter(
     *     name="longitude",
     *     in="query",
     *     description="Location longitude",
     *     required=true,
     *     @OA\Schema(type="number", format="float", example=77.2090)
     * )
     * @OA\Parameter(
     *     name="year",
     *     in="query",
     *     description="Year for calculation",
     *     required=true,
     *     @OA\Schema(type="integer", example=2023)
     * )
     * @OA\Parameter(
     *     name="month",
     *     in="query",
     *     description="Month for calculation (1-12)",
     *     required=true,
     *     @OA\Schema(type="integer", minimum=1, maximum=12, example=12)
     * )
     * @OA\Parameter(
     *     name="day",
     *     in="query",
     *     description="Day for calculation (1-31)",
     *     required=true,
     *     @OA\Schema(type="integer", minimum=1, maximum=31, example=25)
     * )
     * @OA\Parameter(
     *     name="hour",
     *     in="query",
     *     description="Hour for calculation (0-23)",
     *     required=true,
     *     @OA\Schema(type="integer", minimum=0, maximum=23, example=12)
     * )
     * @OA\Parameter(
     *     name="min",
     *     in="query",
     *     description="Minute for calculation (0-59)",
     *     required=true,
     *     @OA\Schema(type="integer", minimum=0, maximum=59, example=0)
     * )
     * @OA\Parameter(
     *     name="sec",
     *     in="query",
     *     description="Second for calculation (0-59)",
     *     required=true,
     *     @OA\Schema(type="integer", minimum=0, maximum=59, example=0)
     * )
     * @OA\Parameter(
     *     name="time_zone",
     *     in="query",
     *     description="Timezone for the calculation",
     *     required=true,
     *     @OA\Schema(type="string", example="+03:30")
     * )
     * @OA\Parameter(
     *     name="dst_hour",
     *     in="query",
     *     description="Daylight Saving Time hours offset",
     *     required=false,
     *     @OA\Schema(type="integer", minimum=0, example=0)
     * )
     * @OA\Parameter(
     *     name="dst_min",
     *     in="query",
     *     description="Daylight Saving Time minutes offset",
     *     required=false,
     *     @OA\Schema(type="integer", minimum=0, example=0)
     * )
     * @OA\Parameter(
     *     name="nesting",
     *     in="query",
     *     description="Nesting level for calculations",
     *     required=false,
     *     @OA\Schema(type="integer", minimum=0, example=0)
     * )
     * @OA\Parameter(
     *     name="varga",
     *     in="query",
     *     description="Varga divisions to calculate (comma-separated)",
     *     required=false,
     *     @OA\Schema(type="string", example="D1,D9")
     * )
     * @OA\Parameter(
     *     name="infolevel",
     *     in="query",
     *     description="Information levels to include (comma-separated)",
     *     required=false,
     *     @OA\Schema(type="string", example="basic,panchanga,transit")
     * )
     * @OA\Response(
     *     response=200,
     *     description="Successful chart calculation",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="chart", type="object"),
     *         @OA\Property(property="duration_of_response", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time")
     *     )
     * )
     * @OA\Response(
     *     response=500,
     *     description="Internal server error",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="error", type="string")
     *     )
     * )
     */
    public function calculate(Request $request): JsonResponse
    {
        $this->logger->info('Calculate chart endpoint accessed');
        $startTime = microtime(true);

        try {
            $requiredParams = ['latitude', 'longitude', 'year', 'month', 'day', 'hour', 'min', 'sec', 'time_zone'];
            $missingParams = [];
            
            foreach ($requiredParams as $param) {
                if (!$request->query->has($param)) {
                    $missingParams[] = $param;
                }
            }
            
            if (!empty($missingParams)) {
                throw new ApiException(400, 'Missing required parameters', ['missing' => $missingParams]);
            }
            
            $params = [
                'latitude' => $request->query->get('latitude'),
                'longitude' => $request->query->get('longitude'),
                'year' => $request->query->get('year'),
                'month' => $request->query->get('month'),
                'day' => $request->query->get('day'),
                'hour' => $request->query->get('hour'),
                'min' => $request->query->get('min'),
                'sec' => $request->query->get('sec'),
                'time_zone' => $request->query->get('time_zone') ?? 'Asia/Tehran',
                'dst_hour' => $request->query->get('dst_hour') ?? 0,
                'dst_min' => $request->query->get('dst_min') ?? 0,
                'nesting' => $request->query->get('nesting') ?? 0,
            ];
            
            $params['varga'] = $request->query->has('varga') 
                ? array_map(fn($item) => trim($item), explode(',', $request->query->get('varga'))) 
                : ['D1'];
                
            $params['infolevel'] = $request->query->has('infolevel') 
                ? array_map(fn($item) => trim($item), explode(',', $request->query->get('infolevel'))) 
                : [];
            
            $result = $this->chart->calculator($params);
            $this->logger->debug('Chart calculated successfully');

            $endTime = microtime(true);
            $duration = number_format($endTime - $startTime, 3);
            $createdAt = (new \DateTime())->format('Y-m-d H:i:s');

            $response = [
                'chart' => $result,
                'duration_of_response' => (float) $duration,
                'created_at' => $createdAt,
            ];

            $this->logger->info('Returning successful response');

            return $this->json($response);
        } catch (ApiException $e) {
            $this->logger->warning('API exception: ' . $e->getMessage(), [
                'status_code' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
            
            return $this->json([
                'error' => $e->getMessage(),
                'details' => $e->getDetails(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('An error occurred: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'error' => 'An internal error occurred',
            ], 500);
        }
    }

    /**
     * @Route("/api/now", name="now", methods={"GET"})
     * @OA\Get(
     *     path="/api/now",
     *     summary="Get current time astrological chart",
     *     description="Calculates an astrological chart for the current moment",
     *     tags={"Chart"}
     * )
     * @OA\Parameter(
     *     name="latitude",
     *     in="query",
     *     description="Location latitude",
     *     required=false,
     *     @OA\Schema(type="number", format="float", example=35.708309)
     * )
     * @OA\Parameter(
     *     name="longitude",
     *     in="query",
     *     description="Location longitude",
     *     required=false,
     *     @OA\Schema(type="number", format="float", example=51.380730)
     * )
     * @OA\Parameter(
     *     name="time_zone",
     *     in="query",
     *     description="Timezone for the calculation",
     *     required=true,
     *     @OA\Schema(type="string", example="+03:30")
     * )
     * @OA\Response(
     *     response=200,
     *     description="Successful chart calculation",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="chart", type="object"),
     *         @OA\Property(property="duration_of_response", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time")
     *     )
     * )
     * @OA\Response(
     *     response=500,
     *     description="Internal server error",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="error", type="string")
     *     )
     * )
     */
    public function getNowChart(Request $request): JsonResponse
    {
        $this->logger->info('Get now chart endpoint accessed');
        $startTime = microtime(true);

        try {
            $defaultLatitude = 35.7219;
            $defaultLongitude = 51.3347;

            $latitude = $request->query->get('latitude') ?? $defaultLatitude;
            $longitude = $request->query->get('longitude') ?? $defaultLongitude;
            $time_zone = $request->query->get('time_zone') ?? "+03:30";
            
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                throw new ApiException(400, 'Invalid coordinates', [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ]);
            }

            $result = $this->chart->calculateNow($latitude, $longitude, $time_zone);
            $this->logger->debug('Chart calculated successfully');

            $endTime = microtime(true);
            $duration = number_format($endTime - $startTime, 3);
            $createdAt = (new \DateTime())->format('Y-m-d H:i:s');

            $response = [
                'chart' => $result,
                'duration_of_response' => (float) $duration,
                'created_at' => $createdAt,
            ];

            $this->logger->info('Returning successful response');

            return $this->json($response);
        } catch (ApiException $e) {
            $this->logger->warning('API exception: ' . $e->getMessage(), [
                'status_code' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
            
            return $this->json([
                'error' => $e->getMessage(),
                'details' => $e->getDetails(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('An error occurred: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'error' => 'An internal error occurred',
            ], 500);
        }
    }
}