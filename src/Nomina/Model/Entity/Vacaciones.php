<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Vacaciones extends TableGateway
{
    private $id;
    private $idemp;
    private $fechaI;
    private $fechaF; 
    private $dias;
    private $diasNh;
    private $valor;
    private $estado;
    private $salario;
    private $valCon;    
    private $promDia;
    private $diasCal; 
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_vacaciones', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->idemp   = $datos["idEmp"];   
        $this->fechaI  = $datos["fecsal"];  
        $this->fechaF  = $datos["fecReg"];  
        $this->dias    = $datos["dias"];  // Dias para disfrute        
        $this->diasNh  = $datos["diasNh"];  
        $this->valor   = $datos["valor"];  
        $this->estado  = $datos["estado"];  
        $this->salario = $datos["salario"];  
        $this->valCon  = $datos["valCon"];  
        $this->promDia = $datos["promDia"];     
        $this->diasCal = $datos["diasCal"];    
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       
       // Fecha del sistema
       $date    = new \DateTime(); 
       $fecSis  = $date->format('Y-m-d H:i');       
              
       $datos=array
       (
           'idEmp'     => $this->idemp,
           'idTnom' => $data['tipo'], // Dias en dinero
           'fechaI'    => $this->fechaI, 
           'fechaF'    => $this->fechaF, 
           'fechaR'    => $data['fecRegR'], // Dia real de retorno 
           'fechaIp'    => $data['fechaIni'], // Dia real de retorno 
           'dias'      => $this->dias, // Dias para disfrute
           'dias31'    => $data['dias31'], // Dias 31
           'diasNh'    => $this->diasNh, 
           'diasNhReal' => $data['diasNhReal'], 
           'sabado'    => $data['sabado'], 
           'domingo'   => $data['domingo'], 
           'diasCal'   => $this->diasCal,
           'valor'     => $this->valor, // Valor vacaciones en disfrute
           'diasDinero' => $data['diasDinero'], // Dias en dinero
           'valorDinero'=> $data['valorDinero'], // valor vacaciones en dinero           
           'estado'    => $this->estado, 
           'salario'   => $this->salario, 
           'valCon'    => $this->valCon, 
           'promDia'   => $this->promDia, 
           'fecDoc'    => $fecSis
        );
       
       if ($id==0) // Nuevo registro
       {
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;          
       }
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
    }
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }    
     
}
?>
