<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class EmpleadosF extends TableGateway
{
    private $id;
    private $lentes;
    private $nombre;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados_f', $adapter, $databaseSchema,$selectResultPrototype);
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(),$idUsu)
    {
      // $id = $data["id"];


       $datos=array
       (
           "idEmp"       => $data["id"],
           "lentes"      => $data["lentes2"],
           "nombres"     => $data["nombre2"],           
           "apellidos"   => $data["apellido2"],           
           "parentesco"  => $data["parentesco"],           
           "sexo"        => $data["sexo2"],                      
           "fechaNac"    => $data["fechaIni"],
           "lentes"      => $data["lentes2"],
           "idNest"      => $data["idNest2"],    
           "instituto"   => $data["comenN"],    
           "limFisica"   => $data["condFis"], 
           "tipCal"      => $data["tipCal"],
           "grado"       => $data["grado"],
           "idTipG"      => $data["tipGrado"],
           "hobbis"      => $data["comenN2"],
           "comentario"  => $data["comenN3"],
           "condMad"     => $data["condicion"],
           "idVoc"       => $data["idVocF"],
           "idLimFis"    => $data["tipLimf"],
           "idConAcdem"  => $data["conAcdem"],
           "idClasAcdem" => $data["clasAcdem"],   
           "idUsu"      => $idUsu,                 
           
        );
       //if ($id==0) // Nuevo registro
          $this->insert($datos);
       //else // Mdificar registro

//          $this->update($datos, array('id' => $id));
    }
    
   public function getRegistroId($id)
   {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row)
       {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
   }     

   public function delRegistro($id)
   {
       $this->delete(array('id' => $id));               
   }

   private function cargaAtributos($datos=array())
   {
      $this->id     = $datos["id"];    
      $this->nombre = $datos["nombres"];             
   }
 //actualizar en nucleo familiar
   public function actRegistroFam($data=array())
   {
       
      $id = $data["id"];
       $datos=array
       (
           
           "lentes"      => $data["lentes3"],
           "nombres"     => $data["nombre3"],           
           "apellidos"   => $data["apellido3"],           
           "parentesco"  => $data["parentesco"],           
           "sexo"        => $data["sexo2"],                      
           "fechaNac"    => $data["fechaIni"],
           "lentes"      => $data["lentes2"],
           "idNest"      => $data["idNest2"],    
           "instituto"   => $data["comenN"],    
           "limFisica"   => $data["condFis"], 
           "tipCal"      => $data["tipCal"],
           "grado"       => $data["grado"],
           "idTipG"      => $data["tipGrado"],
           "hobbis"      => $data["comenN2"],
           "comentario"  => $data["comenN3"],
           "condMad"     => $data["condicion"],
           "idVoc"       => $data["idVocF"],
           "idLimFis"    => $data["tipLimf"],
           "idConAcdem"  => $data["conAcdem"],
           "idClasAcdem" => $data["clasAcdem"],                 
             
                        
           
        );
    
        $this->update($datos, array('id' => $id));
  }
}
?>
