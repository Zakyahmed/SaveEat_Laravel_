<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Invendu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    /**
     * Récupérer les réservations pour l'association de l'utilisateur
     */
    public function forAssociation(Request $request): JsonResponse
    {
        $user = $request->user();
        $association = $user->associations()->first();
        
        if (!$association) {
            return response()->json(['message' => 'Vous n\'avez pas d\'association'], 404);
        }
        
        $query = Reservation::with(['invendu', 'invendu.restaurant'])
            ->where('res_asso_id', $association->asso_id);
        
        // Filtres optionnels
        if ($request->has('statut')) {
            $query->where('res_statut', $request->statut);
        }
        
        if ($request->has('date_debut')) {
            $query->where('res_date', '>=', $request->date_debut);
        }
        
        if ($request->has('date_fin')) {
            $query->where('res_date', '<=', $request->date_fin);
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'res_date_collecte');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $reservations = $query->paginate($perPage);
        
        return response()->json($reservations);
    }
    
    /**
     * Récupérer les réservations pour le restaurant de l'utilisateur
     */
    public function forRestaurant(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurants()->first();
        
        if (!$restaurant) {
            return response()->json(['message' => 'Vous n\'avez pas de restaurant'], 404);
        }
        
        $query = Reservation::with(['invendu', 'association'])
            ->whereHas('invendu', function($q) use ($restaurant) {
                $q->where('inv_rest_id', $restaurant->rest_id);
            });
        
        // Filtres optionnels
        if ($request->has('statut')) {
            $query->where('res_statut', $request->statut);
        }
        
        if ($request->has('date_debut')) {
            $query->where('res_date', '>=', $request->date_debut);
        }
        
        if ($request->has('date_fin')) {
            $query->where('res_date', '<=', $request->date_fin);
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'res_date_collecte');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $reservations = $query->paginate($perPage);
        
        return response()->json($reservations);
    }
    
    /**
     * Créer une nouvelle réservation
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $association = $user->associations()->first();
        
        if (!$association) {
            return response()->json(['message' => 'Vous n\'avez pas d\'association'], 403);
        }
        
        if (!$association->asso_valide) {
            return response()->json(['message' => 'Votre association n\'est pas encore validée'], 403);
        }
        
        $validated = $request->validate([
            'invendu_id' => 'required|exists:sea_invendu,inv_id',
            'date_collecte' => 'required|date|after:now',
            'commentaire' => 'nullable|string',
        ]);
        
        // Vérifier si l'invendu est disponible
        $invendu = Invendu::findOrFail($validated['invendu_id']);
        
        if ($invendu->inv_statut !== 'disponible') {
            return response()->json(['message' => 'Cet invendu n\'est plus disponible'], 422);
        }
        
        if ($invendu->inv_date_limite < now()) {
            return response()->json(['message' => 'La date limite de cet invendu est dépassée'], 422);
        }
        
        // Vérifier que la date de collecte est bien dans la plage autorisée
        if (Carbon::parse($validated['date_collecte']) > Carbon::parse($invendu->inv_date_limite)) {
            return response()->json(['message' => 'La date de collecte doit être avant la date limite de l\'invendu'], 422);
        }
        
        if (Carbon::parse($validated['date_collecte']) < Carbon::parse($invendu->inv_date_disponibilite)) {
            return response()->json(['message' => 'La date de collecte doit être après la date de disponibilité'], 422);
        }
        
        // Vérifier que le restaurant est validé
        $restaurant = $invendu->restaurant;
        
        if (!$restaurant->rest_valide) {
            return response()->json(['message' => 'Ce restaurant n\'est pas validé'], 422);
        }
        
        // Créer la réservation
        $reservation = new Reservation();
        $reservation->res_date = now();
        $reservation->res_date_collecte = $validated['date_collecte'];
        $reservation->res_statut = 'en_attente';
        $reservation->res_commentaire = $validated['commentaire'] ?? null;
        $reservation->res_inv_id = $invendu->inv_id;
        $reservation->res_asso_id = $association->asso_id;
        $reservation->save();
        
        // Mettre à jour le statut de l'invendu
        $invendu->inv_statut = 'reserve';
        $invendu->save();
        
        return response()->json(['message' => 'Réservation créée avec succès', 'reservation' => $reservation], 201);
    }
    
    /**
     * Mettre à jour une réservation
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $reservation = Reservation::with(['invendu', 'association'])->findOrFail($id);
        
        // Vérifier que l'utilisateur a le droit de modifier cette réservation
        $isAssociationOwner = $user->associations()->where('asso_id', $reservation->res_asso_id)->exists();
        $isRestaurantOwner = $user->restaurants()->whereHas('invendus', function($q) use ($reservation) {
            $q->where('inv_id', $reservation->res_inv_id);
        })->exists();
        
        if (!$isAssociationOwner && !$isRestaurantOwner && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Différentes validations selon le type d'utilisateur
        if ($isAssociationOwner) {
            $validated = $request->validate([
                'date_collecte' => 'sometimes|date|after:now',
                'commentaire' => 'nullable|string',
                'statut' => 'sometimes|in:annule',
            ]);
            
            // Une association ne peut qu'annuler une réservation
            if (isset($validated['statut']) && $validated['statut'] != 'annule') {
                return response()->json(['message' => 'Vous ne pouvez qu\'annuler une réservation'], 422);
            }
        } else {
            // Restaurant ou admin
            $validated = $request->validate([
                'statut' => 'required|in:accepte,refuse,termine,annule',
                'commentaire' => 'nullable|string',
            ]);
        }
        
        // Vérifier que le statut peut être modifié
        if ($reservation->res_statut == 'annule' || $reservation->res_statut == 'termine') {
            return response()->json(['message' => 'Cette réservation ne peut plus être modifiée'], 422);
        }
        
        // Mise à jour des champs
        if (isset($validated['date_collecte'])) {
            $reservation->res_date_collecte = $validated['date_collecte'];
        }
        
        if (isset($validated['commentaire'])) {
            $reservation->res_commentaire = $validated['commentaire'];
        }
        
        if (isset($validated['statut'])) {
            $reservation->res_statut = $validated['statut'];
            
            // Mettre à jour le statut de l'invendu en conséquence
            if ($validated['statut'] == 'annule' || $validated['statut'] == 'refuse') {
                $reservation->invendu->inv_statut = 'disponible';
                $reservation->invendu->save();
            } elseif ($validated['statut'] == 'termine') {
                $reservation->invendu->inv_statut = 'distribue';
                $reservation->invendu->save();
            }
        }
        
        $reservation->save();
        
        return response()->json(['message' => 'Réservation mise à jour avec succès', 'reservation' => $reservation]);
    }
    
    /**
     * Supprimer une réservation (uniquement par l'association)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $reservation = Reservation::with('invendu')->findOrFail($id);
        
        // Vérifier que l'utilisateur a le droit de supprimer cette réservation
        $isAssociationOwner = $user->associations()->where('asso_id', $reservation->res_asso_id)->exists();
        
        if (!$isAssociationOwner && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // On ne peut supprimer que les réservations en attente ou acceptées
        if (!in_array($reservation->res_statut, ['en_attente', 'accepte'])) {
            return response()->json(['message' => 'Cette réservation ne peut pas être supprimée'], 422);
        }
        
        // Rendre l'invendu à nouveau disponible
        $reservation->invendu->inv_statut = 'disponible';
        $reservation->invendu->save();
        
        // Supprimer la réservation
        $reservation->delete();
        
        return response()->json(['message' => 'Réservation supprimée avec succès']);
    }
    
    /**
     * Mettre à jour le statut d'une réservation (raccourci pour les restaurants)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $reservation = Reservation::with(['invendu', 'invendu.restaurant'])->findOrFail($id);
        
        // Vérifier que l'utilisateur est bien le propriétaire du restaurant
        $restaurant = $reservation->invendu->restaurant;
        $isRestaurantOwner = $user->restaurants()->where('rest_id', $restaurant->rest_id)->exists();
        
        if (!$isRestaurantOwner && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        $request->validate([
            'statut' => 'required|in:accepte,refuse,termine,annule',
            'commentaire' => 'nullable|string',
        ]);
        
        // Vérifier que le statut peut être modifié
        if ($reservation->res_statut == 'annule' || $reservation->res_statut == 'termine') {
            return response()->json(['message' => 'Cette réservation ne peut plus être modifiée'], 422);
        }
        
        // Mise à jour du statut
        $reservation->res_statut = $request->statut;
        
        if ($request->has('commentaire')) {
            $reservation->res_commentaire = $request->commentaire;
        }
        
        // Mettre à jour le statut de l'invendu en conséquence
        if ($request->statut == 'annule' || $request->statut == 'refuse') {
            $reservation->invendu->inv_statut = 'disponible';
            $reservation->invendu->save();
        } elseif ($request->statut == 'termine') {
            $reservation->invendu->inv_statut = 'distribue';
            $reservation->invendu->save();
        }
        
        $reservation->save();
        
        return response()->json(['message' => 'Statut mis à jour avec succès', 'reservation' => $reservation]);
    }
}